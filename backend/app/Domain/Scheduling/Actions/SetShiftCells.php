<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.02 free selection in the grid: an arbitrary set of (person, local day) cells gets the same
 * template (or is cleared) in ONE transaction — all or nothing.
 */
final class SetShiftCells
{
    private const MAX_CELLS = 2000;

    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<array{user_id: int, date: string}>  $cells */
    public function __invoke(User $actor, array $cells, ?ShiftTemplate $template): int
    {
        if (count($cells) > self::MAX_CELLS) {
            throw new BusinessRuleViolation('Demasiadas celdas a la vez: hacelo por partes (máximo 2000).', 'too_many_cells');
        }

        $byUser = [];
        foreach ($cells as $cell) {
            $byUser[(int) $cell['user_id']][$cell['date']] = true;
        }

        $active = User::query()->where('company_id', $actor->company_id)->where('status', UserStatus::Active)->whereKey(array_keys($byUser))->count();
        if ($active !== count($byUser)) {
            throw new BusinessRuleViolation('Alguna persona no existe o está dada de baja.', 'member_inactive');
        }

        $touched = DB::transaction(function () use ($actor, $byUser, $template) {
            $touched = [];
            foreach ($byUser as $userId => $dates) {
                $dates = array_keys($dates);
                ShiftAssignment::query()->forCompany($actor->company_id)->where('user_id', $userId)->whereIn('date', $dates)->delete();
                foreach ($dates as $date) {
                    if ($template !== null) {
                        ShiftAssignment::query()->create([
                            'company_id' => $actor->company_id, 'user_id' => $userId, 'date' => $date,
                            'shift_template_id' => $template->id, 'starts_at' => $template->starts_at,
                            'ends_at' => $template->ends_at, 'mode' => $template->mode, 'assigned_by' => $actor->id,
                        ]);
                    }
                }
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
