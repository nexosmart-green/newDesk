<?php

namespace App\Domain\TimeTracking\Events;

use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RF-05.07 / RF-09.04: a project crossed a consumption threshold (80 / 100 by default).
 */
final class HourThresholdReached
{
    use Dispatchable;

    public function __construct(public readonly Project $project, public readonly int $percent) {}
}
