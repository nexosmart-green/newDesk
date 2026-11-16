<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.08: new order of the whole checklist. The ids must be exactly the task's items, each once,
 * so a stale screen never drops or duplicates a line.
 */
final class ReorderChecklist
{
    /**
     * @param  list<int>  $orderedIds
     * @return Collection<int, TaskChecklistItem>
     */
    public function __invoke(Task $task, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($task, $orderedIds) {
            Task::query()->forCompany($task->company_id)->whereKey($task->id)->lockForUpdate()->value('id');
            $items = TaskChecklistItem::query()->forCompany($task->company_id)->where('task_id', $task->id)->get()->keyBy('id');

            $sortedRequested = $orderedIds;
            $current = $items->keys()->all();
            sort($sortedRequested);
            sort($current);
            if ($sortedRequested !== $current) {
                throw new BusinessRuleViolation(
                    'La lista de subtareas cambió mientras la ordenabas. Actualizá la página y probá de nuevo.',
                    'checklist_order_mismatch',
                );
            }

            foreach (array_values($orderedIds) as $index => $id) {
                $item = $items[$id];
                if ($item->position !== $index + 1) {
                    $item->position = $index + 1;
                    $item->save();
                }
            }

            return $task->checklistItems()->with('doneBy')->get();
        });
    }
}
