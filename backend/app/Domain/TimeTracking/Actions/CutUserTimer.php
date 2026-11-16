<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerCutter;
use Illuminate\Database\Eloquent\Builder;

/**
 * CA-01.10: a person who can no longer work here (deactivated, or removed from the project of their running
 * timer) keeps no timer running.
 */
final class CutUserTimer
{
    public function __construct(private readonly TimerCutter $cutter) {}

    /** @return int entries cut (0 or 1) */
    public function __invoke(User $user): int
    {
        $entry = TimeEntry::query()->runningFor($user)->first();

        return $entry === null ? 0
            : $this->cutter->cut($user->company_id, $entry->project_id, StopReason::UserDeactivated, fn (Builder $q) => $q->where('user_id', $user->id));
    }
}
