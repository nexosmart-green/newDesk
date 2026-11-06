<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\ProjectFolder;
use Illuminate\Support\Collection;

/**
 * How the monitoreo MCP names projects (monitoreo mcpFindProject, mcpScope, D-053): by id, slug or
 * part of the name, and "yours" = the ones you lead or are a member of plus the ones nobody was
 * assigned to.
 */
final class ProjectLookup
{
    /** Every project, by name (monitoreo ListAllProjects). @return Collection<int, Project> */
    public function all(): Collection
    {
        return Project::query()->with('client')->orderBy('name')->orderBy('id')->get();
    }

    /** The user's projects (lead or secondary) and those without developers; every project with $all. @return Collection<int, Project> */
    public function scoped(User $user, bool $all): Collection
    {
        $projects = $this->all();
        if ($all) {
            return $projects;
        }
        $developers = ProjectAdmin::developers($projects->modelKeys());

        return $projects->filter(function (Project $project) use ($developers, $user) {
            $ids = array_column($developers[$project->id] ?? [], 'user_id');

            return $ids === [] || in_array($user->id, $ids, true);
        })->values();
    }

    /** "12", "playinn" or "Club Olimpo" to one project; an exact slug or name wins over partial matches. */
    public function find(string $query): Project
    {
        $query = trim($query);
        $projects = $this->all();
        if (ctype_digit($query) && ($project = $projects->firstWhere('id', (int) $query)) !== null) {
            return $project;
        }
        $needle = mb_strtolower($query);
        $exact = $projects->first(fn (Project $project) => mb_strtolower((string) $project->slug) === $needle || mb_strtolower($project->name) === $needle);
        if ($exact !== null) {
            return $exact;
        }
        $matches = $projects->filter(fn (Project $project) => str_contains(mb_strtolower($project->name.' '.$project->slug), $needle))->values();
        $quoted = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return match ($matches->count()) {
            1 => $matches->first(),
            0 => throw new BusinessRuleViolation("no encontré ningún proyecto que coincida con {$quoted} (probá projects_list)", 'project_not_found', 404),
            default => throw new BusinessRuleViolation("{$quoted} coincide con varios proyectos: "
                .$matches->map(fn (Project $project) => "{$project->name} ({$project->slug})")->implode(', '), 'project_ambiguous'),
        };
    }

    /**
     * The servers the projects live on: those of their linked folders and of their components (monitoreo ProjectServerIDs).
     *
     * @param  list<int>  $projectIds
     * @return list<int>
     */
    public function serverIds(array $projectIds): array
    {
        return ProjectFolder::query()->whereIn('project_id', $projectIds)->pluck('server_id')
            ->merge(Component::query()->whereIn('project_id', $projectIds)->whereNotNull('server_id')->pluck('server_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
