<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

final class AddProjectMember
{
    public function __construct(
        private readonly EnsureProjectAssignable $assignable,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, Project $project, int $userId): User
    {
        $member = $this->assignable->member($userId, $project->company_id);

        DB::transaction(function () use ($actor, $project, $member) {
            if ($project->hasMember($member)) {
                return;
            }

            $project->members()->attach($member->id, ['added_by' => $actor->id, 'added_at' => now()]);
            ($this->recordActivity)('project.member_added', $project, $actor, after: ['member' => $member->fullName()], projectId: $project->id);
        });

        return $member;
    }
}
