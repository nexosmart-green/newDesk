<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateTask
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($actor, $task, $data) {
            $before = $this->snapshot($task);
            $task->fill(Arr::only($data, Task::EDITABLE))->save();

            if ($task->wasChanged()) {
                ($this->recordActivity)('task.updated', $task, $actor, $before, $this->snapshot($task), $task->project_id);
            }

            return $task;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Task $task): array
    {
        return [
            'title' => $task->title,
            'priority' => $task->priority->value,
            'starts_on' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toDateString(),
            'estimate_seconds' => $task->estimate_seconds,
        ];
    }
}
