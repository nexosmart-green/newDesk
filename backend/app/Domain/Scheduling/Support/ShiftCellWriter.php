<?php

namespace App\Domain\Scheduling\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * RF-06.02: a grid cell is "person × local day". Writing a cell replaces what was there with the template's
 * times and mode; a null template just clears it. Callers run it inside their transaction.
 */
final class ShiftCellWriter
{
    /** Cells per operation: beyond this the request must be split (keeps one transaction short). */
    public const MAX_CELLS = 2000;

    /** @throws BusinessRuleViolation */
    public function ensureWithinLimit(int $cells): void
    {
        if ($cells > self::MAX_CELLS) {
            throw new BusinessRuleViolation('Demasiadas celdas a la vez: hacelo por partes (máximo '.self::MAX_CELLS.').', 'too_many_cells');
        }
    }

    /** @param  list<string>  $dates  local days (Y-m-d) */
    public function write(User $actor, int $userId, array $dates, ?ShiftTemplate $template): void
    {
        ShiftAssignment::query()->forCompany($actor->company_id)->where('user_id', $userId)->whereIn('date', $dates)->delete();

        if ($template === null) {
            return;
        }

        foreach ($dates as $date) {
            ShiftAssignment::query()->create([
                'company_id' => $actor->company_id,
                'user_id' => $userId,
                'date' => $date,
                'shift_template_id' => $template->id,
                'starts_at' => $template->starts_at,
                'ends_at' => $template->ends_at,
                'mode' => $template->mode,
                'assigned_by' => $actor->id,
            ]);
        }
    }
}
