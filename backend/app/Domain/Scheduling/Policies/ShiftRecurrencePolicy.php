<?php

namespace App\Domain\Scheduling\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use Illuminate\Auth\Access\Response;

/**
 * RF-06.03: recurring shifts are part of building the schedule (`shifts.manage`). Another company's rule is 404.
 */
class ShiftRecurrencePolicy
{
    public function update(User $user, ShiftRecurrence $recurrence): Response
    {
        if ($recurrence->company_id !== $user->company_id) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::ShiftsManage)
            ? Response::allow()
            : Response::deny('Solo quien arma los turnos puede cambiar un turno fijo.');
    }

    public function delete(User $user, ShiftRecurrence $recurrence): Response
    {
        return $this->update($user, $recurrence);
    }
}
