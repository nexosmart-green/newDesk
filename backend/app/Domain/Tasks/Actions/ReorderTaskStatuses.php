<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * Board column order (RF-04.03). Receives every status id of the company in the new order.
 */
final class ReorderTaskStatuses
{
    /** @param  list<int>  $orderedIds */
    public function __invoke(int $companyId, array $orderedIds): void
    {
        DB::transaction(function () use ($companyId, $orderedIds) {
            $current = TaskStatus::query()->forCompany($companyId)->lockForUpdate()->pluck('id')->sort()->values()->all();
            $requested = collect($orderedIds)->sort()->values()->all();

            if ($current !== $requested) {
                throw new BusinessRuleViolation('El orden tiene que incluir todos los estados, una sola vez.', 'status_order_incomplete');
            }

            foreach ($orderedIds as $index => $id) {
                TaskStatus::query()->forCompany($companyId)->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }
}
