<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Auth\Access\Response;

/**
 * Same rule as categories: created inline by anyone who creates tasks, edited with `projects.manage`.
 */
class TaskLabelPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TaskLabel $label): Response
    {
        return $user->hasPermission(Permission::ProjectsManage)
            ? Response::allow()
            : Response::deny('Solo quien gestiona proyectos puede editar o borrar etiquetas.');
    }

    public function delete(User $user, TaskLabel $label): Response
    {
        return $this->update($user, $label);
    }
}
