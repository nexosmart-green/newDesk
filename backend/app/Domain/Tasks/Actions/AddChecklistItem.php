<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.08: adds a line at the end of the task checklist. The task row is locked so two people adding
 * at once neither share a position nor go over the limit.
 */
final class AddChecklistItem
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Task $task, string $text): TaskChecklistItem
    {
        return DB::transaction(function () use ($actor, $task, $text) {
            Task::query()->forCompany($task->company_id)->whereKey($task->id)->lockForUpdate()->value('id');
            $items = TaskChecklistItem::query()->forCompany($task->company_id)->where('task_id', $task->id);

            if ($items->count() >= TaskChecklistItem::MAX_PER_TASK) {
                throw new BusinessRuleViolation(
                    'La tarea ya tiene '.TaskChecklistItem::MAX_PER_TASK.' subtareas, que es el máximo. Borrá alguna o dividí la tarea.',
                    'checklist_limit',
                );
            }

            $item = TaskChecklistItem::query()->create([
                'company_id' => $task->company_id,
                'task_id' => $task->id,
                'text' => $text,
                'position' => ((int) $items->max('position')) + 1,
                'created_by' => $actor->id,
            ]);

            ($this->recordActivity)('checklist.added', $task, $actor, after: ['text' => $text], projectId: $task->project_id);

            return $item;
        });
    }
}
