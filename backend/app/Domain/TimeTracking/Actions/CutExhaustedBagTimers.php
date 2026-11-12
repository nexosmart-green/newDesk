<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\TimerCutter;
use Illuminate\Support\Carbon;

/** Minute job (RF-05.06): bag projects whose running timers used up what was left. */
final class CutExhaustedBagTimers
{
    public function __construct(private readonly TimerCutter $cutter) {}

    /** @return int entries cut */
    public function __invoke(?Carbon $now = null): int
    {
        $now ??= now();
        $cut = 0;

        $projects = Project::query()->withoutGlobalScopes()
            ->where('type', ProjectType::Bag)
            ->whereIn('id', TimeEntry::query()->withoutGlobalScopes()->running()->select('project_id'))
            ->get();

        foreach ($projects as $project) {
            if (HourBag::of($project, $now)->isExhausted()) {
                $cut += $this->cutter->cut($project->company_id, $project->id, StopReason::BudgetExhausted, endAt: $now);
            }
        }

        return $cut;
    }
}
