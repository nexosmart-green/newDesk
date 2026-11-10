<?php

namespace App\Domain\TimeTracking\Events;

use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A timer was cut by the system (RF-05.06/10/17). Notifications tell the person (RF-09.05)
 * and, for budget cuts, the Admin.
 */
final class TimerAutoStopped
{
    use Dispatchable;

    public function __construct(public readonly TimeEntry $entry) {}
}
