<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Hours are loaded only into bag projects (RF-05.04). Shared by AllocateHours and the MCP tools that describe
 * an allocation before it runs, so a person never confirms one that is bound to fail.
 */
final class EnsureProjectHasBag
{
    public function __invoke(Project $project): void
    {
        if (! $project->type->hasHourCap()) {
            throw new BusinessRuleViolation("{$project->name} es interno: no tiene bolsa de horas.", 'project_without_bag');
        }
    }
}
