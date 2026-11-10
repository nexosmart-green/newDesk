<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Events\TaskClosed;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * Moving to a "closed" kind completes the task and stops its timers via TaskClosed (CA-04.03).
 */
final class ChangeTaskStatus
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Task $task, TaskStatus $status): Task
    {
        return DB::transaction(function () use ($actor, $task, $status) {
            $task = Task::query()->with('status')->lockForUpdate()->findOrFail($task->id);
            $previous = $task->status;

            if ($previous->id === $status->id) {
                return $task;
            }

            $task->forceFill([
                'status_id' => $status->id,
                'completed_at' => $status->isClosed() ? ($task->completed_at ?? now()) : null,
            ])->save();
            $task->setRelation('status', $status);

            ($this->recordActivity)('task.status_changed', $task, $actor,
                ['status' => $previous->name], ['status' => $status->name], $task->project_id);

            if ($status->isClosed() && ! $previous->isClosed()) {
                TaskClosed::dispatch($task, $actor);
            }

            return $task;
        });
    }
}
