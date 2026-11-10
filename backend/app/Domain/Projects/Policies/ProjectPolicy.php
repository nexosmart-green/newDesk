<?php

namespace App\Domain\Projects\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * CA-03.03: a non-member gets 404, not 403 — we don't even confirm the project exists.
     */
    public function view(User $user, Project $project): Response
    {
        return $this->canSee($user, $project) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectsManage);
    }

    public function update(User $user, Project $project): Response
    {
        return $this->visibleAnd($user, $project, $user->hasPermission(Permission::ProjectsManage));
    }

    public function manageMembers(User $user, Project $project): Response
    {
        return $this->visibleAnd($user, $project, $user->hasPermission(Permission::MembersManage));
    }

    public static function seesAllProjects(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectsManage);
    }

    private function canSee(User $user, Project $project): bool
    {
        return $user->company_id === $project->company_id
            && (self::seesAllProjects($user) || $project->hasMember($user));
    }

    private function visibleAnd(User $user, Project $project, bool $allowed): Response
    {
        if (! $this->canSee($user, $project)) {
            return Response::denyAsNotFound();
        }

        return $allowed ? Response::allow() : Response::deny();
    }
}
