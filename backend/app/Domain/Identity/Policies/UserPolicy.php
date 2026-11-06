<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

/**
 * Users of other companies never reach this policy: the `{user}` binding turns them into 404.
 */
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
        return $this->manages($actor, $user);
    }

    public function resendInvitation(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }

    /** Colleagues see each other's public profile (directory, "¿quién sabe React?"). */
    public function viewProfile(User $actor, User $user): bool
    {
        return $actor->company_id === $user->company_id;
    }

    /** RNF-06: address, CUIL, emergency contact… only for the person and `users.manage`. */
    public function viewSensitive(User $actor, User $user): bool
    {
        return $actor->is($user) || $this->manages($actor, $user);
    }

    public function updateProfile(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }

    /** CA-01.09: rates are Admin-only, read and write. */
    public function viewRates(User $actor, User $user): bool
    {
        return $actor->company_id === $user->company_id && $actor->hasPermission(Permission::RatesView);
    }

    public function addRate(User $actor, User $user): bool
    {
        return $this->viewRates($actor, $user) && $actor->hasPermission(Permission::UsersManage);
    }

    private function manages(User $actor, User $user): bool
    {
        return $actor->company_id === $user->company_id && $actor->hasPermission(Permission::UsersManage);
    }
}
