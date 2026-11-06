<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Projects\Models\Project;

/**
 * Request-scoped memo of HourBag per project, so listing 200 tasks of 3 projects costs 3 bag reads, not 200.
 * Read-only use (presentation); domain rules always compute a fresh bag under the project lock.
 */
final class HourBagRegistry
{
    /** @var array<int, HourBag> */
    private array $bags = [];

    public function for(Project $project): HourBag
    {
        return $this->bags[$project->id] ??= HourBag::of($project);
    }
}
