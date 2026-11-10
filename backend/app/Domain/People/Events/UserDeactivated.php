<?php

namespace App\Domain\People\Events;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised inside the deactivation transaction. Other modules react without People knowing them,
 * e.g. TimeTracking stops the running timer with comment_pending (CA-01.10, RF-05.17).
 */
final class UserDeactivated
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly User $actor) {}
}
