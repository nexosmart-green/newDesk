<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Events\ProjectMemberRemoved;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

final class RemoveProjectMember
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Project $project, User $member): void
    {
        if ($project->leader_user_id === $member->id) {
            throw new BusinessRuleViolation('Es el responsable del proyecto: asigná otro responsable antes de quitarlo.', 'member_is_leader');
        }

        DB::transaction(function () use ($actor, $project, $member) {
            if ($project->members()->detach($member->id) === 0) {
                return;
            }

            ($this->recordActivity)('project.member_removed', $project, $actor, ['member' => $member->fullName()], projectId: $project->id);
            ProjectMemberRemoved::dispatch($project, $member, $actor);
        });
    }
}
