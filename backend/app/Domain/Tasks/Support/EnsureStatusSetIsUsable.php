<?php

namespace App\Domain\Tasks\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Enums\StatusKind;
use App\Domain\Tasks\Models\TaskStatus;

/**
 * The workflow needs at least one open status (where tasks start) and one closed (where they end).
 * Call inside the transaction that changed the set, so a violation rolls it back.
 */
final class EnsureStatusSetIsUsable
{
    public function __invoke(int $companyId): void
    {
        $kinds = TaskStatus::query()->forCompany($companyId)->pluck('kind')->map(fn (StatusKind $kind) => $kind->value)->all();

        if (! in_array(StatusKind::Open->value, $kinds, true) || ! in_array(StatusKind::Closed->value, $kinds, true)) {
            throw new BusinessRuleViolation(
                'Tiene que quedar al menos un estado de tipo "abierta" y uno de tipo "cerrada".',
                'status_set_incomplete',
            );
        }
    }
}
