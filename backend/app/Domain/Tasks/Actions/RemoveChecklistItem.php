<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.08: deletes a checklist line; the task history keeps its text.
 */
final class RemoveChecklistItem
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, TaskChecklistItem $item): void
    {
        DB::transaction(function () use ($actor, $item) {
            $task = $item->loadMissing('task')->task;
            $item->delete();

            ($this->recordActivity)('checklist.removed', $task, $actor, before: ['text' => $item->text], projectId: $task->project_id);
        });
    }
}
