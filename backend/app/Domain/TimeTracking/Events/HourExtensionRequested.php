<?php

namespace App\Domain\TimeTracking\Events;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RF-05.08 / RF-09.06.
 */
final class HourExtensionRequested
{
    use Dispatchable;

    public function __construct(public readonly HourExtensionRequest $request, public readonly User $requester) {}
}
