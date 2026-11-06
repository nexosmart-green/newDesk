<?php

namespace App\Domain\Clients\Policies;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class ClientPolicy
{
    /** Whoever creates projects or follows monitoring needs the client list. */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ClientsManage)
            || $user->hasPermission(Permission::ProjectsManage)
            || $user->hasPermission(Permission::MonitoringView);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ClientsManage);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission(Permission::ClientsManage);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermission(Permission::ClientsManage);
    }
}
