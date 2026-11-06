<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project, or updates it when one is given (RF-03.01). The leader is always
 * a member, so whoever is responsible can see it (RF-03.03).
 */
final class SaveProject
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $attributes */
    public function __invoke(array $attributes, User $actor, ?Project $project = null): Project
    {
        $clientId = $attributes['client_id'] ?? null;
        if ($clientId !== null && $clientId !== $project?->client_id
            && ! Client::query()->whereKey($clientId)->where('active', true)->exists()) {
            // CA-02.01: an inactive client does not take new projects.
            throw new BusinessRuleViolation('El cliente está inactivo: no admite proyectos nuevos.', 'client_inactive');
        }

        return DB::transaction(function () use ($attributes, $actor, $project) {
            $before = $project?->only(array_keys($attributes));
            $project ??= new Project(['company_id' => $actor->company_id]);
            $project->fill($attributes)->save();

            if ($project->leader_user_id !== null) {
                $project->members()->syncWithoutDetaching([$project->leader_user_id]);
            }

            ($this->recordActivity)(
                $before === null ? 'project.created' : 'project.updated',
                $project,
                $actor,
                $before === null ? null : $this->serialize($before),
                $this->serialize($project->only(array_keys($attributes))),
                $project->id,
            );

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function serialize(array $values): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }
}
