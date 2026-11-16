<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Queries\ActiveCompanyPeople;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Support\ShiftCellWriter;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.02 free selection in the grid: an arbitrary set of (person, local day) cells gets the same
 * template (or is cleared) in ONE transaction — all or nothing.
 */
final class SetShiftCells
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly ActiveCompanyPeople $activePeople,
        private readonly ShiftCellWriter $cellWriter,
    ) {}

    /** @param  list<array{user_id: int, date: string}>  $cells */
    public function __invoke(User $actor, array $cells, ?ShiftTemplate $template): int
    {
        $this->cellWriter->ensureWithinLimit(count($cells));

        $byUser = [];
        foreach ($cells as $cell) {
            $byUser[(int) $cell['user_id']][$cell['date']] = true;
        }

        $this->activePeople->resolveOrFail($actor->company_id, array_keys($byUser));

        $touched = DB::transaction(function () use ($actor, $byUser, $template) {
            $touched = [];
            foreach ($byUser as $userId => $dates) {
                $dates = array_keys($dates);
                $this->cellWriter->write($actor, $userId, $dates, $template);
                $touched[$userId] = $dates;
            }

            ($this->recordActivity)('shifts.assigned', $actor, $actor, after: [
                'template' => $template?->name,
                'cells' => array_sum(array_map('count', $touched)),
            ]);

            return $touched;
        });

        ShiftsChanged::dispatch($touched, $actor);

        return array_sum(array_map('count', $touched));
    }
}
