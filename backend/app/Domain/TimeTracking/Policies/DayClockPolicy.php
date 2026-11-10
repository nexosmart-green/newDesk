<?php

namespace App\Domain\TimeTracking\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\DayClock;
use Illuminate\Auth\Access\Response;

class DayClockPolicy
{
    /** RF-05.19: only its owner splits a day; someone else's does not exist for you. */
    public function split(User $user, DayClock $day): Response
    {
        return $day->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
