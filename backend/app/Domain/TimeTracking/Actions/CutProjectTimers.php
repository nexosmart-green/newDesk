<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Support\TimerCutter;

/** CA-03.08: closing a project stops every timer running on it. */
final class CutProjectTimers
{
    public function __construct(private readonly TimerCutter $cutter) {}

    /** @return int entries cut */
    public function __invoke(Project $project): int
    {
        return $this->cutter->cut($project->company_id, $project->id, StopReason::ProjectClosed);
    }
}
