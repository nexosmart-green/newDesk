<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Queries\ActiveCompanyPeople;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Support\ShiftCellWriter;
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
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly ActiveCompanyPeople $activePeople,
        private readonly ShiftCellWriter $cellWriter,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $weekdays  ISO 1..7; empty = every day
     * @return int cells written
     */
    public function __invoke(User $actor, array $userIds, ?ShiftTemplate $template, string $from, string $to, array $weekdays = []): int
    {
        $people = $this->activePeople->resolveOrFail($actor->company_id, $userIds);

        $dates = $this->dates($from, $to, $weekdays);
        $this->cellWriter->ensureWithinLimit($people->count() * count($dates));

        $touched = DB::transaction(function () use ($actor, $people, $template, $dates) {
            $touched = [];
            foreach ($dates === [] ? [] : $people as $person) {
                $this->cellWriter->write($actor, $person->id, $dates, $template);
                $touched[$person->id] = $dates;
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
            throw new BusinessRuleViolation('Elegí un rango de hasta un año, con la fecha de fin igual o posterior al inicio.', 'invalid_range');
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
