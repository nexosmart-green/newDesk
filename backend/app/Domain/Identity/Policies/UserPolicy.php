<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersManage);
    }

    public function invite(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersManage);
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $actor->hasPermission(Permission::UsersManage) && $actor->company_id === $user->company_id;
    }

    public function resendInvitation(User $actor, User $user): bool
    {
        return $this->changeRole($actor, $user);
    }
}
