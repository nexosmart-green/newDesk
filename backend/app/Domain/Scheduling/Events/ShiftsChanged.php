<?php

namespace App\Domain\Scheduling\Events;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * RF-06.08 / RF-09.07: one event per bulk operation; Notifications sends one message per affected person.
 */
final class ShiftsChanged
{
    use Dispatchable;

    /** @param  array<int, list<string>>  $datesByUserId  local dates touched, per person */
    public function __construct(public readonly array $datesByUserId, public readonly User $actor) {}
}
