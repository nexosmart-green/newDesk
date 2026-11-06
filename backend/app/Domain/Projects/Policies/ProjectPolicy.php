<?php

namespace App\Domain\Projects\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // the list itself is filtered by Project::visibleTo()
    }

    /** CA-03.03: a non-member gets 404, not 403 — we do not confirm the project exists. */
    public function view(User $user, Project $project): Response
    {
        return $user->hasPermission(Permission::ProjectsManage) || $project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectsManage);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectsManage);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectsManage);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::MembersManage);
    }
}
