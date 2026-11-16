<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * Board column order (RF-04.03). Receives every status id of the company in the new order.
 */
final class ReorderTaskStatuses
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<int>  $orderedIds */
    public function __invoke(User $actor, array $orderedIds): void
    {
        DB::transaction(function () use ($actor, $orderedIds) {
            $companyId = $actor->company_id;
            $previousOrder = TaskStatus::query()->forCompany($companyId)->orderBy('position')->lockForUpdate()->pluck('id')->all();

            if (collect($previousOrder)->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
                throw new BusinessRuleViolation('El orden tiene que incluir todos los estados, una sola vez.', 'status_order_incomplete');
            }

            foreach ($orderedIds as $index => $id) {
                TaskStatus::query()->forCompany($companyId)->whereKey($id)->update(['position' => $index + 1]);
            }

            ($this->recordActivity)('task_status.reordered', $actor->company, $actor, ['ids' => $previousOrder], ['ids' => $orderedIds]);
        });
    }
}
