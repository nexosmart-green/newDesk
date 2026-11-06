<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-03.03. The leader stays a member whatever the list says.
 */
final class SyncProjectMembers
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<int>  $userIds */
    public function __invoke(Project $project, array $userIds, User $actor): Project
    {
        return DB::transaction(function () use ($project, $userIds, $actor) {
            $before = $project->members()->pluck('users.id')->sort()->values()->all();

            $ids = array_values(array_unique([...$userIds, ...array_filter([$project->leader_user_id])]));
            $project->members()->sync($ids);
            sort($ids);

            ($this->recordActivity)('project.members_synced', $project, $actor, ['members' => $before], ['members' => $ids], $project->id);

            return $project;
        });
    }
}
