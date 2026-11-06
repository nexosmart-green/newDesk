<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * When a running timer counts as forgotten (RF-05.10, P-07 default, RF-11.03 setting).
 * Today: started_at + `forgotten_timer_max_hours` (10 h). E5 (shifts) extends this with
 * "end of shift + `forgotten_timer_after_shift_hours`" when the person has a shift that day.
 */
class ForgottenTimerDeadline
{
    public function for(TimeEntry $entry): Carbon
    {
        $maxHours = (int) $entry->user->company->setting('forgotten_timer_max_hours');

        return $entry->started_at->copy()->addHours($maxHours);
    }
}
