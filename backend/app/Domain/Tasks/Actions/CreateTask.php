<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Events\TaskAssigned;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.01. Codes `<prefix>-<n>` come from a per-project sequence incremented under a row lock
 * (CA-04.01): no gaps, no duplicates, even with simultaneous creations. Never `max()+1`.
 */
final class CreateTask
{
    public function __construct(
        private readonly ResolveProjectMembers $resolveMembers,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Task::EDITABLE fields
     * @param  list<int>  $assigneeIds
     */
    public function __invoke(User $actor, Project $project, array $data, array $assigneeIds = []): Task
    {
        $task = DB::transaction(function () use ($actor, $project, $data, $assigneeIds) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->status->isClosed()) {
                throw new BusinessRuleViolation(
                    "{$project->name} está {$project->status->label()}: no admite tareas nuevas.",
                    'project_closed',
                );
            }

            $assignees = ($this->resolveMembers)($project, $assigneeIds);

            $project->task_sequence++;
            $project->save();

            $task = Task::query()->create([
                ...Arr::only($data, Task::EDITABLE),
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'number' => $project->task_sequence,
                'code' => "{$project->code_prefix}-{$project->task_sequence}",
                'status_id' => TaskStatus::initialFor($project->company_id)->id,
                'created_by' => $actor->id,
            ]);
            $task->assignees()->attach(array_map(fn (User $user) => $user->id, $assignees));

            ($this->recordActivity)('task.created', $task, $actor, after: [
                'code' => $task->code,
                'title' => $task->title,
                'assignees' => array_map(fn (User $user) => $user->fullName(), $assignees),
            ], projectId: $project->id);

            return [$task, $assignees];
        });

        [$task, $assignees] = $task;
        $others = array_values(array_filter($assignees, fn (User $user) => ! $user->is($actor)));
        if ($others !== []) {
            TaskAssigned::dispatch($task, $others, $actor);
        }

        return $task;
    }
}
