<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;

/**
 * Roles of other companies never reach the policy: the company scope turns them into 404.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        // Assigning a role to a user requires seeing the list.
        return $user->hasPermission(Permission::RolesManage) || $user->hasPermission(Permission::UsersManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission(Permission::RolesManage);
    }
}
