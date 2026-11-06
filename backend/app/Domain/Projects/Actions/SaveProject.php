<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a project, or updates it when one is given (RF-03.01). The leader is always
 * a member, so whoever is responsible can see it (RF-03.03). A new project without a slug
 * gets one from its name, as monitoreo does: it is in the client's portal URL (RF-13.07).
 */
final class SaveProject
{
    /** Slugs the portal SPA uses for its own routes (/portal/login): a project there would be shadowed. */
    public const RESERVED_SLUGS = ['login'];

    public const SLUG_TAKEN = 'Ese identificador ya está en uso.';

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

        if ($project === null && blank($attributes['slug'] ?? null)) {
            $attributes['slug'] = $this->availableSlug((string) $attributes['name'], $actor->company_id);
        } elseif (in_array($attributes['slug'] ?? null, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages(['slug' => self::SLUG_TAKEN]);
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

    /** "Café Nueva Córdoba" → cafe-nueva-cordoba; a taken one gets a number up to -20 (monitoreo portal.Slugify). */
    private function availableSlug(string $name, int $companyId): string
    {
        $folded = strtr(mb_strtolower($name), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $base = trim(substr(trim((string) preg_replace('/[^a-z0-9]+/u', '-', $folded), '-'), 0, 50), '-') ?: 'proyecto';

        foreach ([$base, ...array_map(fn (int $n) => "{$base}-{$n}", range(2, 20))] as $candidate) {
            if (! in_array($candidate, self::RESERVED_SLUGS, true)
                && ! Project::query()->forCompany($companyId)->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['slug' => self::SLUG_TAKEN]);
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
