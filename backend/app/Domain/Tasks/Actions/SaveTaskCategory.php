<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Support\Facades\DB;

/**
 * Creates (when $category is null) or renames a task category.
 */
final class SaveTaskCategory
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, string $name, ?TaskCategory $category = null): TaskCategory
    {
        return DB::transaction(function () use ($actor, $name, $category) {
            $before = $category?->only('name');
            $category ??= new TaskCategory(['company_id' => $actor->company_id]);
            $category->name = $name;
            $category->save();

            ($this->recordActivity)($before === null ? 'task_category.created' : 'task_category.renamed', $category, $actor, $before, $category->only('name'));

            return $category;
        });
    }
}
