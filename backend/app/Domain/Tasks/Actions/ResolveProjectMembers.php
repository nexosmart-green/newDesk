<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * RF-03.03 / RF-04.01: tasks are assigned (and people mentioned) only among active project members.
 */
final class ResolveProjectMembers
{
    /**
     * @param  list<int>  $userIds
     * @return list<User>
     */
    public function __invoke(Project $project, array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $members = $project->members()->whereKey($userIds)->where('users.status', UserStatus::Active)->get();
        if ($members->count() !== count($userIds)) {
            throw new BusinessRuleViolation(
                "Solo se puede asignar o mencionar a miembros activos de {$project->name}.",
                'not_a_project_member',
            );
        }

        return $members->all();
    }
}
