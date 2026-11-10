<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Support\Facades\DB;

/**
 * Removes the label from every task that had it (pivot cascades).
 */
final class DeleteTaskLabel
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, TaskLabel $label): void
    {
        DB::transaction(function () use ($actor, $label) {
            $tasks = $label->tasks()->withTrashed()->count();
            ($this->recordActivity)('task_label.deleted', $label, $actor, [...$label->only('name', 'color'), 'tasks' => $tasks]);
            $label->delete();
        });
    }
}
