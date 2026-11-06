<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Tasks\Models\Task;
use Illuminate\Auth\Access\Response;

/**
 * Tasks inherit project visibility (404 for outsiders, CA-03.03).
 * P-04: any project member creates, edits, comments and moves tasks; assigning needs `tasks.assign`.
 */
class TaskPolicy
{
    public function view(User $user, Task $task): Response
    {
        return $this->participates($user, $task->project) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, Project $project): Response
    {
        return $this->participates($user, $project) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Task $task): Response
    {
        return $this->view($user, $task);
    }

    public function comment(User $user, Task $task): Response
    {
        return $this->view($user, $task);
    }

    public function assign(User $user, Task $task): Response
    {
        if (! $this->participates($user, $task->project)) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::TasksAssign) ? Response::allow() : Response::deny();
    }

    /** Members work on the project; `projects.manage` sees and acts on every project. */
    private function participates(User $user, Project $project): bool
    {
        return $user->company_id === $project->company_id
            && (ProjectPolicy::seesAllProjects($user) || $project->hasMember($user));
    }
}
