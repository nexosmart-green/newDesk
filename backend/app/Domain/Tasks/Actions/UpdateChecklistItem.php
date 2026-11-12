<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.08: renames and/or checks a checklist line. Checking records who and when; unchecking clears it.
 * Only real changes reach the task history.
 */
final class UpdateChecklistItem
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, TaskChecklistItem $item, ?string $text = null, ?bool $done = null): TaskChecklistItem
    {
        return DB::transaction(function () use ($actor, $item, $text, $done) {
            // Two people ticking at once: the second sees it already done and logs nothing.
            $item = TaskChecklistItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $task = $item->loadMissing('task')->task;

            if ($text !== null && $text !== $item->text) {
                $before = $item->text;
                $item->text = $text;
                $item->save();
                ($this->recordActivity)('checklist.renamed', $task, $actor, ['text' => $before], ['text' => $text], $task->project_id);
            }

            if ($done !== null && $done !== $item->isDone()) {
                $item->forceFill(['done_at' => $done ? now() : null, 'done_by' => $done ? $actor->id : null])->save();
                ($this->recordActivity)($done ? 'checklist.checked' : 'checklist.unchecked', $task, $actor, after: ['text' => $item->text], projectId: $task->project_id);
            }

            return $item;
        });
    }
}
