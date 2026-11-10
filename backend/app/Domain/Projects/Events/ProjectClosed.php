<?php

namespace App\Domain\Projects\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised inside the transaction that finishes/cancels a project.
 * TimeTracking listens to stop running timers (CA-03.08).
 */
final class ProjectClosed
{
    use Dispatchable;

    public function __construct(public readonly Project $project, public readonly User $actor) {}
}
