<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateProject
{
    public function __construct(
        private readonly EnsureProjectAssignable $assignable,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, Project $project, array $data): Project
    {
        $data = Arr::only($data, Project::EDITABLE);
        if (isset($data['code_prefix'])) {
            $data['code_prefix'] = mb_strtolower((string) $data['code_prefix']);
        }

        if (array_key_exists('client_id', $data) && $data['client_id'] !== $project->client_id) {
            $this->assignable->client($data['client_id'], $project->company_id);
        }

        return DB::transaction(function () use ($actor, $project, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if (isset($data['code_prefix']) && $data['code_prefix'] !== $project->code_prefix && $project->prefixIsLocked()) {
                throw new BusinessRuleViolation(
                    "El prefijo «{$project->code_prefix}» ya está en uso en códigos de tareas y no se puede cambiar.",
                    'prefix_locked',
                );
            }

            $leaderChanged = array_key_exists('leader_user_id', $data) && $data['leader_user_id'] !== $project->leader_user_id;
            if ($leaderChanged && $data['leader_user_id'] !== null) {
                $leader = $this->assignable->member((int) $data['leader_user_id'], $project->company_id);
                $project->members()->syncWithoutDetaching([$leader->id => ['added_by' => $actor->id, 'added_at' => now()]]);
            }

            $before = $this->snapshot($project);
            $project->fill($data)->save();

            ($this->recordActivity)('project.updated', $project, $actor, $before, $this->snapshot($project), $project->id);

            return $project;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Project $project): array
    {
        return [
            ...$project->only('client_id', 'name', 'code_prefix', 'description', 'leader_user_id'),
            'type' => $project->type->value,
            'starts_on' => $project->starts_on?->toDateString(),
            'ends_on' => $project->ends_on?->toDateString(),
        ];
    }
}
