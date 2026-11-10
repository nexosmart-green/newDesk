<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * RF-03.03 from monitoreo's developers form: the list becomes the members, one by one through
 * AddProjectMember / RemoveProjectMember (their activity, their timer stop). The leader always stays.
 */
final class SyncProjectMembers
{
    public function __construct(
        private readonly AddProjectMember $addMember,
        private readonly RemoveProjectMember $removeMember,
    ) {}

    /** @param  list<int>  $userIds */
    public function __invoke(Project $project, array $userIds, User $actor): Project
    {
        return DB::transaction(function () use ($project, $userIds, $actor) {
            $wanted = array_values(array_unique([...$userIds, ...array_filter([$project->leader_user_id])]));
            $current = $project->members()->get();

            foreach ($current->whereNotIn('id', $wanted) as $member) {
                ($this->removeMember)($actor, $project, $member);
            }
            foreach (array_diff($wanted, $current->modelKeys()) as $userId) {
                ($this->addMember)($actor, $project, $userId);
            }

            return $project;
        });
    }
}
