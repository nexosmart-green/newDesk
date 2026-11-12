<?php

namespace App\Domain\Tasks\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\TaskCategory;
use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Category and labels of a task must come from the task's own company catalogs (web and MCP alike).
 */
final class ResolveTaskClassification
{
    public function category(int $companyId, ?int $categoryId): ?TaskCategory
    {
        if ($categoryId === null) {
            return null;
        }

        return TaskCategory::query()->forCompany($companyId)->find($categoryId)
            ?? throw new BusinessRuleViolation('La categoría elegida ya no existe. Recargá y elegí otra, o creala.', 'unknown_task_category');
    }

    /**
     * @param  list<int>  $labelIds
     * @return Collection<int, TaskLabel>
     */
    public function labels(int $companyId, array $labelIds): Collection
    {
        $labelIds = array_values(array_unique($labelIds));
        $labels = $labelIds === [] ? new Collection : TaskLabel::query()->forCompany($companyId)->whereKey($labelIds)->orderBy('name')->get();

        if ($labels->count() !== count($labelIds)) {
            throw new BusinessRuleViolation('Una de las etiquetas elegidas ya no existe. Recargá y elegí de nuevo.', 'unknown_task_label');
        }

        return $labels;
    }
}
