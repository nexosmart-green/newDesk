<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\ForgottenTimerDeadline;
use App\Domain\TimeTracking\Support\TimerCutter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Minute job (RF-05.10, P-07): timers running past their deadline end AT the deadline. */
final class CutForgottenTimers
{
    public function __construct(
        private readonly TimerCutter $cutter,
        private readonly ForgottenTimerDeadline $deadline,
    ) {}

    /** @return int entries cut */
    public function __invoke(?Carbon $now = null): int
    {
        $now ??= now();
        $cut = 0;

        $running = TimeEntry::query()->withoutGlobalScopes()->running()->with('user.company')->get();
        foreach ($running as $entry) {
            $deadline = $this->deadline->for($entry);
            if ($deadline->lessThanOrEqualTo($now)) {
                $cut += $this->cutter->cut($entry->company_id, $entry->project_id, StopReason::Forgotten,
                    fn (Builder $q) => $q->whereKey($entry->id), $deadline);
            }
        }

        return $cut;
    }
}
