<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateTask
{
    public function __construct(
        private readonly ResolveTaskClassification $classification,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array<string, mixed>  $data  Task::EDITABLE fields, plus optional `label_ids` (list<int>, replaces them all) */
    public function __invoke(User $actor, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($actor, $task, $data) {
            $before = $this->snapshot($task);

            $task->fill(Arr::only($data, Task::EDITABLE));
            if ($task->isDirty('category_id')) {
                $task->setRelation('category', $this->classification->category($task->company_id, $task->category_id));
            }
            $descriptionChanged = $task->isDirty('description');
            $task->save();

            $labelsChanged = false;
            if (array_key_exists('label_ids', $data)) {
                $labels = $this->classification->labels($task->company_id, $data['label_ids']);
                $changes = $task->labels()->sync($labels->modelKeys());
                $labelsChanged = $changes['attached'] !== [] || $changes['detached'] !== [];
                $task->unsetRelation('labels');
            }

            if ($task->wasChanged() || $labelsChanged) {
                $after = $this->snapshot($task) + ($descriptionChanged ? ['description_changed' => true] : []);
                ($this->recordActivity)('task.updated', $task, $actor, $before, $after, $task->project_id);
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
            'category' => $task->category?->name,
            'labels' => $task->labels()->pluck('task_labels.name')->all(),
        ];
    }
}
