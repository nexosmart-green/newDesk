<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Nobody hands out (or takes away from someone) more power than they have.
 * With editable roles, `users.manage` can be delegated (e.g. to HR); without this guard that person could
 * promote themselves or others to Admin, or deactivate an Admin.
 */
final class RoleGrantGuard
{
    /** The actor may assign $role only if they hold every permission in it. */
    public function assertCanGrant(User $actor, Role $role): void
    {
        $missing = array_diff($role->permissionValues(), $actor->role->permissionValues());

        if ($missing !== [] || ($role->is_system && ! $actor->role->is_system)) {
            throw new BusinessRuleViolation("No podés asignar el rol {$role->name}: tiene permisos que vos no tenés.", 'role_above_actor', 403);
        }
    }

    /** The actor may manage $target only if $target's role does not exceed the actor's. */
    public function assertCanManage(User $actor, User $target): void
    {
        $this->assertCanGrant($actor, $target->role);
    }

    public function assertNotSelf(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new BusinessRuleViolation('No podés cambiar tu propio rol.', 'cannot_change_own_role', 403);
        }
    }
}
