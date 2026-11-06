<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Events\HourThresholdReached;

/**
 * RF-05.07: fires once each time consumption crosses a configured threshold (RF-11.03, default 80/100).
 * When an extension lowers the percentage, the level goes back down so the alert can fire again later.
 * Call with the project row locked.
 */
final class EvaluateHourAlerts
{
    public function __invoke(Project $project, int $consumedSeconds): void
    {
        if (! $project->type->hasHourCap()) {
            return;
        }

        $percent = $project->allocated_seconds > 0
            ? (int) floor($consumedSeconds * 100 / $project->allocated_seconds)
            : 100;

        $thresholds = $project->loadMissing('company')->company->setting('hour_alert_thresholds');
        sort($thresholds);
        $level = 0;
        foreach ($thresholds as $threshold) {
            if ($percent >= $threshold) {
                $level = (int) $threshold;
            }
        }

        if ($level === $project->hour_alert_level) {
            return;
        }

        $raised = $level > $project->hour_alert_level;
        $project->forceFill(['hour_alert_level' => $level])->save();

        if ($raised) {
            HourThresholdReached::dispatch($project, $level);
        }
    }
}
