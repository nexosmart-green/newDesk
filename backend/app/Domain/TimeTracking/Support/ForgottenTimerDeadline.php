<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * When a running timer counts as forgotten (RF-05.10, P-07 default, RF-11.03 settings):
 *  - with a shift that day still open at start → end of that shift + `forgotten_timer_after_shift_hours` (1 h);
 *  - otherwise → started_at + `forgotten_timer_max_hours` (10 h).
 * Never later than the 10 h cap.
 */
class ForgottenTimerDeadline
{
    public function for(TimeEntry $entry): Carbon
    {
        $company = $entry->user->company;
        $cap = $entry->started_at->copy()->addHours((int) $company->setting('forgotten_timer_max_hours'));

        $localDate = $entry->started_at->copy()->setTimezone($company->timezone)->toDateString();
        $shiftEnds = ShiftAssignment::query()->forCompany($entry->company_id)
            ->where('user_id', $entry->user_id)
            ->whereIn('date', [$localDate, Carbon::parse($localDate)->subDay()->toDateString()])
            ->get()
            ->map(fn (ShiftAssignment $shift) => $shift->interval($company->timezone))
            ->filter(fn (array $interval) => $interval[1]->greaterThan($entry->started_at))
            ->map(fn (array $interval) => $interval[1]);

        if ($shiftEnds->isEmpty()) {
            return $cap;
        }

        $deadline = $shiftEnds->min()->copy()->addHours((int) $company->setting('forgotten_timer_after_shift_hours'));

        return $deadline->lessThan($cap) ? $deadline : $cap;
    }
}
