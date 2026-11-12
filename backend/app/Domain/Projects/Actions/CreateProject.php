<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\EnsureProjectAssignable;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * RF-03.01/02/03. The leader is always a member. Initial hours are loaded by RF-05.04 (AllocateHours).
 */
final class CreateProject
{
    public function __construct(
        private readonly EnsureProjectAssignable $assignable,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Project::EDITABLE fields
     * @param  list<int>  $memberIds
     */
    public function __invoke(User $actor, array $data, array $memberIds = []): Project
    {
        $this->assignable->client($data['client_id'] ?? null, $actor->company_id);

        $leaderId = $data['leader_user_id'] ?? null;
        $memberIds = array_values(array_unique(array_filter([...$memberIds, $leaderId])));
        $members = array_map(fn (int $id) => $this->assignable->member($id, $actor->company_id), $memberIds);

        return DB::transaction(function () use ($actor, $data, $members) {
            $project = Project::query()->create([
                ...Arr::only($data, Project::EDITABLE),
                'code_prefix' => mb_strtolower((string) $data['code_prefix']),
                'company_id' => $actor->company_id,
            ]);

            $project->members()->attach(collect($members)->mapWithKeys(fn (User $member) => [
                $member->id => ['added_by' => $actor->id, 'added_at' => now()],
            ])->all());

            ($this->recordActivity)('project.created', $project, $actor, after: [
                ...$project->only('name', 'code_prefix'),
                'type' => $project->type->value,
                'members' => array_map(fn (User $member) => $member->fullName(), $members),
            ], projectId: $project->id);

            return $project;
        });
    }
}
