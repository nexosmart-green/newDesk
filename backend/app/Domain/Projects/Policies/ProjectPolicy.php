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
        return $this->visibleAnd($user, $project, $user->hasPermission(Permission::ProjectsManage), 'Solo quien gestiona proyectos puede editar este proyecto.');
    }

    public function manageMembers(User $user, Project $project): Response
    {
        return $this->visibleAnd($user, $project, $user->hasPermission(Permission::MembersManage), 'Solo quien gestiona miembros puede sumar o quitar personas del proyecto.');
    }

    /** RF-05.04: load, extend or correct the bag (`hours.allocate`). Web and MCP alike. */
    public function allocateHours(User $user, Project $project): Response
    {
        return $this->visibleAnd($user, $project, $user->hasPermission(Permission::HoursAllocate), 'Tu rol no tiene permiso para cargar horas.', 'cannot_allocate');
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

    private function visibleAnd(User $user, Project $project, bool $allowed, string $denial, ?string $code = null): Response
    {
        if (! $this->canSee($user, $project)) {
            return Response::denyAsNotFound();
        }

        return $allowed ? Response::allow() : Response::deny($denial, $code);
    }
}
