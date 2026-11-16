<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Auth\Access\Response;

/**
 * Anyone who creates tasks adds categories inline from the task form; renaming or deleting
 * changes every task that uses it, so it needs `projects.manage`.
 */
class TaskCategoryPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TaskCategory $category): Response
    {
        return $user->hasPermission(Permission::ProjectsManage)
            ? Response::allow()
            : Response::deny('Solo quien gestiona proyectos puede editar o borrar categorías.');
    }

    public function delete(User $user, TaskCategory $category): Response
    {
        return $this->update($user, $category);
    }
}
