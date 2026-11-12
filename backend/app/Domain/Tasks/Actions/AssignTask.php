<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Events\TaskAssigned;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Support\ResolveProjectMembers;
use Illuminate\Support\Facades\DB;

/**
 * Sets the exact list of assignees (1..n project members, P-21: each one tracks their own time).
 */
final class AssignTask
{
    public function __construct(
        private readonly ResolveProjectMembers $resolveMembers,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  list<int>  $assigneeIds */
    public function __invoke(User $actor, Task $task, array $assigneeIds): Task
    {
        $assignees = ($this->resolveMembers)($task->project, $assigneeIds);

        $added = DB::transaction(function () use ($actor, $task, $assignees) {
            $before = $task->assignees()->get();
            $changes = $task->assignees()->sync(array_map(fn (User $user) => $user->id, $assignees));

            if ($changes['attached'] === [] && $changes['detached'] === []) {
                return [];
            }

            ($this->recordActivity)('task.assigned', $task, $actor,
                ['assignees' => $before->map(fn (User $user) => $user->fullName())->all()],
                ['assignees' => array_map(fn (User $user) => $user->fullName(), $assignees)],
                $task->project_id);

            return array_values(array_filter($assignees, fn (User $user) => in_array($user->id, $changes['attached'], true)));
        });

        $others = array_values(array_filter($added, fn (User $user) => ! $user->is($actor)));
        if ($others !== []) {
            TaskAssigned::dispatch($task, $others, $actor);
        }

        return $task->load('assignees');
    }
}
