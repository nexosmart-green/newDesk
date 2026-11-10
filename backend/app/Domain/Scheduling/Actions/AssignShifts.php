<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.02: a grid cell is "person × local day". Assigning sets that day's shift (replacing what was there);
 * a null template clears the day. Bulk = several people × a date range filtered by ISO weekdays (1 = Monday).
 */
final class AssignShifts
{
    private const MAX_CELLS = 2000;

    public function __construct(private readonly RecordActivity $recordActivity) {}

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $weekdays  ISO 1..7; empty = every day
     * @return int cells written
     */
    public function __invoke(User $actor, array $userIds, ?ShiftTemplate $template, string $from, string $to, array $weekdays = []): int
    {
        $people = User::query()->where('company_id', $actor->company_id)->team()->where('status', UserStatus::Active)->whereKey($userIds)->get();
        if ($people->count() !== count(array_unique($userIds))) {
            throw new BusinessRuleViolation('Alguna persona no existe o está dada de baja.', 'member_inactive');
        }

        $dates = $this->dates($from, $to, $weekdays);
        if ($people->count() * count($dates) > self::MAX_CELLS) {
            throw new BusinessRuleViolation('Demasiados días a la vez: hacelo por partes (máximo 2000 celdas).', 'too_many_cells');
        }

        $touched = DB::transaction(function () use ($actor, $people, $template, $dates) {
            $touched = [];
            foreach ($people as $person) {
                ShiftAssignment::query()->forCompany($actor->company_id)->where('user_id', $person->id)->whereIn('date', $dates)->delete();
                foreach ($dates as $date) {
                    if ($template !== null) {
                        ShiftAssignment::query()->create([
                            'company_id' => $actor->company_id,
                            'user_id' => $person->id,
                            'date' => $date,
                            'shift_template_id' => $template->id,
                            'starts_at' => $template->starts_at,
                            'ends_at' => $template->ends_at,
                            'mode' => $template->mode,
                            'assigned_by' => $actor->id,
                        ]);
                    }
                    $touched[$person->id][] = $date;
                }
            }

            ($this->recordActivity)('shifts.assigned', $actor, $actor, after: [
                'template' => $template?->name,
                'people' => $people->map(fn (User $person) => $person->fullName())->all(),
                'from' => $dates[0] ?? null,
                'to' => end($dates) ?: null,
            ]);

            return $touched;
        });

        ShiftsChanged::dispatch($touched, $actor);

        return array_sum(array_map('count', $touched));
    }

    /** @return list<string> */
    private function dates(string $from, string $to, array $weekdays): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw new BusinessRuleViolation('Rango de fechas inválido (máximo un año).', 'invalid_range');
        }

        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($weekdays === [] || in_array($day->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }
}
