<?php

namespace App\Domain\Projects\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when someone leaves a project, so other modules can react (e.g. Tasks unassigns, TimeTracking stops a timer).
 */
final class ProjectMemberRemoved
{
    use Dispatchable;

    public function __construct(public readonly Project $project, public readonly User $member, public readonly User $actor) {}
}
