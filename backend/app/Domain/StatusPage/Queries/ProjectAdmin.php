<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The team's view of client projects (monitoreo ProjectAdmin and ProjectRow, D-048, D-053). The
 * developers are newDesk's: the lead is the project's responsible, the secondaries its other
 * members (ADR-0002 §6).
 */
final class ProjectAdmin
{
    public function __construct(private readonly LinkedFolders $folders) {}

    /** @return array<string, mixed> */
    public function show(Project $project): array
    {
        $project->loadMissing('client');
        $components = Component::query()->with('server:id,name')->where('project_id', $project->id)->orderBy('position')->orderBy('id')->get();
        [$status, $phrase] = StatusPage::overall($components);

        return [
            'id' => $project->id,
            'client_id' => $project->client_id,
            'client_name' => $project->client->displayName(),
            'slug' => $project->slug,
            'code_prefix' => $project->code_prefix,
            'name' => $project->name,
            'primary_color' => $project->primary_color,
            'has_logo' => $project->logo_path !== null,
            'logo_updated_at' => $project->logo_updated_at?->toIso8601ZuluString(),
            'domain' => $project->domain,
            'domain_expires_at' => $project->domain_expires_at?->toIso8601ZuluString(),
            'domain_expires_manual' => $project->domain_expires_manual?->toDateString(),
            'domain_checked_at' => $project->domain_checked_at?->toIso8601ZuluString(),
            'errors_url' => $project->errors_url,
            // The token itself never leaves the backend; reading the raw column avoids even decrypting it.
            'has_errors_token' => $project->getRawOriginal('errors_token') !== null,
            'discord_channel_id' => $project->discord_channel_id,
            'active' => $project->portal_active,
            'status' => $status,
            'phrase' => $phrase,
            'components' => $components->map(fn (Component $component) => $component->toAdminPayload())->all(),
            'folders' => $this->folders->forProject($project),
            'developers' => self::developers([$project->id])[$project->id] ?? [],
        ];
    }

    /**
     * The project list rows, in the given order.
     *
     * @param  Collection<int, Project>  $projects  with `client` loaded
     * @return list<array<string, mixed>>
     */
    public function rows(Collection $projects): array
    {
        $ids = $projects->modelKeys();
        $components = Component::query()->whereIn('project_id', $ids)->get()->groupBy('project_id');
        $developers = self::developers($ids);
        $vulnerabilities = Vulnerabilities::load();

        return $projects->map(fn (Project $project) => [
            'id' => $project->id,
            'client_id' => $project->client_id,
            'client_name' => $project->client->displayName(),
            'slug' => $project->slug,
            'name' => $project->name,
            'active' => $project->portal_active,
            'status' => StatusPage::overall($components[$project->id] ?? [])[0],
            'components' => count($components[$project->id] ?? []),
            'logo_url' => ProjectLogo::url($project, forClient: false),
            'developers' => $developers[$project->id] ?? [],
            'vulns' => $vulnerabilities->counts($project->id),
        ])->values()->all();
    }

    /**
     * monitoreo's DeveloperView per project: the lead first, then the secondaries by email.
     *
     * @param  list<int>  $projectIds
     * @return array<int, list<array{user_id: int, email: string, role: string}>>
     */
    public static function developers(array $projectIds): array
    {
        $leaders = Project::query()->whereIn('id', $projectIds)->whereNotNull('leader_user_id')->pluck('leader_user_id', 'id');
        $people = DB::table('users')->whereIn('id', $leaders->values())->pluck('email', 'id');
        $members = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')
            ->whereIn('project_members.project_id', $projectIds)->orderBy('users.email')
            ->get(['project_members.project_id', 'users.id', 'users.email']);

        $out = [];
        foreach ($leaders as $projectId => $leaderId) {
            $out[$projectId][] = ['user_id' => $leaderId, 'email' => $people[$leaderId], 'role' => 'lead'];
        }
        foreach ($members as $member) {
            if ($member->id !== ($leaders[$member->project_id] ?? null)) {
                $out[$member->project_id][] = ['user_id' => $member->id, 'email' => $member->email, 'role' => 'secondary'];
            }
        }

        return $out;
    }
}
