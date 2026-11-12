<?php

namespace App\Domain\TimeTracking\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Auth\Access\Response;

/**
 * RF-05.15: `time.manage` corrects anyone's entries; a person corrects their own (the window is a domain rule).
 * Entries of other companies never reach here (company scope → 404).
 */
class TimeEntryPolicy
{
    public function update(User $user, TimeEntry $entry): Response
    {
        if ($user->hasPermission(Permission::TimeManage) || $entry->user_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /** RF-05.15: deleting is Admin-only (`time.manage`); people only correct their own. */
    public function delete(User $user, TimeEntry $entry): Response
    {
        if ($user->hasPermission(Permission::TimeManage)) {
            return Response::allow();
        }

        return $entry->user_id === $user->id ? Response::deny('Solo un administrador puede borrar registros de horas.') : Response::denyAsNotFound();
    }

    /**
     * RF-05.17: only the owner writes the comment of an entry the system cut. Whoever may see other people's
     * entries (`time.manage`) gets the reason; anyone else does not learn the entry exists.
     */
    public function completeComment(User $user, TimeEntry $entry): Response
    {
        if ($entry->user_id === $user->id) {
            return Response::allow();
        }

        return $user->hasPermission(Permission::TimeManage)
            ? Response::deny('Solo quien registró las horas puede completar su comentario.', 'not_entry_owner')
            : Response::denyAsNotFound();
    }
}
