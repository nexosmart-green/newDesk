<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Support\Facades\DB;

/**
 * Tasks that used it are left without a category (FK nullOnDelete).
 */
final class DeleteTaskCategory
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, TaskCategory $category): void
    {
        DB::transaction(function () use ($actor, $category) {
            $tasks = $category->tasks()->withTrashed()->count();
            ($this->recordActivity)('task_category.deleted', $category, $actor, [...$category->only('name'), 'tasks' => $tasks]);
            $category->delete();
        });
    }
}
