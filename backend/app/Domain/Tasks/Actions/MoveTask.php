<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskImage;
use App\Domain\TimeTracking\Actions\TransferTaskHours;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.12: moves a task (with its hours, comments and the assignees who belong to the destination)
 * to another project. It takes the next code of the destination; the old code becomes an alias.
 * Both projects are locked in id order (same order as timers) so this never deadlocks with them.
 */
final class MoveTask
{
    public function __construct(
        private readonly TransferTaskHours $transferHours,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @return array{task: Task, seconds_moved: int, unassigned: list<string>} */
    public function __invoke(User $actor, Task $task, Project $destination): array
    {
        if ($task->project_id === $destination->id) {
            throw new BusinessRuleViolation("{$task->code} ya está en {$destination->name}.", 'same_project');
        }

        return DB::transaction(function () use ($actor, $task, $destination) {
            $projects = Project::query()->forCompany($task->company_id)
                ->whereKey([$task->project_id, $destination->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $projects[$task->project_id];
            $to = $projects[$destination->id];
            $task = Task::query()->forCompany($task->company_id)->lockForUpdate()->findOrFail($task->id);

            if ($to->status->isClosed()) {
                throw new BusinessRuleViolation("{$to->name} está {$to->status->label()}: no admite tareas.", 'project_closed');
            }

            $seconds = ($this->transferHours)($task, $from, $to);
            $oldCode = $task->code;

            DB::table('task_code_aliases')->insertOrIgnore(['company_id' => $task->company_id, 'task_id' => $task->id, 'code' => $oldCode, 'created_at' => now()]);
            // If the task comes back to a project where its old code lives as an alias, that alias is now its real code again.
            $to->task_sequence++;
            $to->save();
            $newCode = "{$to->code_prefix}-{$to->task_sequence}";
            DB::table('task_code_aliases')->where('company_id', $task->company_id)->where('code', $newCode)->delete();

            $task->forceFill(['project_id' => $to->id, 'number' => $to->task_sequence, 'code' => $newCode])->save();
            $unassigned = $this->keepOnlyDestinationMembers($task, $to);
            $this->moveDescriptionImages($task, $from, $to);

            $details = ['from' => $oldCode, 'to' => $newCode, 'seconds' => $seconds, 'unassigned' => $unassigned];
            ($this->recordActivity)('task.moved_out', $task, $actor, after: [...$details, 'project' => $to->name], projectId: $from->id);
            ($this->recordActivity)('task.moved_in', $task, $actor, after: [...$details, 'project' => $from->name], projectId: $to->id);

            return ['task' => $task, 'seconds_moved' => $seconds, 'unassigned' => $unassigned];
        });
    }

    /**
     * Images are authorized by their project: the ones this description shows travel with the task,
     * or the destination's members would get broken images.
     */
    private function moveDescriptionImages(Task $task, Project $from, Project $to): void
    {
        preg_match_all('#/api/v1/task-images/([0-9a-f-]{36})#i', (string) $task->description, $matches);
        if ($matches[1] === []) {
            return;
        }

        TaskImage::query()->where('project_id', $from->id)->whereIn('uuid', $matches[1])->update(['project_id' => $to->id]);
    }

    /** @return list<string> names removed because they are not active members of the destination */
    private function keepOnlyDestinationMembers(Task $task, Project $destination): array
    {
        $memberIds = $destination->members()->where('users.status', UserStatus::Active)->pluck('users.id')->all();
        $leaving = $task->assignees()->whereKeyNot($memberIds)->get();

        if ($leaving->isNotEmpty()) {
            $task->assignees()->detach($leaving->modelKeys());
        }

        return $leaving->map(fn (User $user) => $user->fullName())->values()->all();
    }
}
