<?php

namespace App\Domain\TimeTracking\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;

/** RF-05.19 / CA-05.19f: a day split loaded more than the bag had left (`seconds` over what was available). */
final class HourBagOverdrawn
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly User $user,
        public readonly int $seconds,
    ) {}
}
