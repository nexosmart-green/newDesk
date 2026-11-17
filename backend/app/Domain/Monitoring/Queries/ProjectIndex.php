<?php

namespace App\Domain\Monitoring\Queries;

use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Sites\SiteTargets;
use App\Domain\StatusPage\Models\ProjectFolder;

/**
 * GET inventory/projects (monitoreo view.ProjectIndex): every project folder the agents found,
 * with the domains nginx serves from it, sorted by name. Servers without a readable `projects`
 * section are left out. `linked` is the client project the folder belongs to (D-044), or null.
 */
final class ProjectIndex
{
    /** @return list<array<string, mixed>> */
    public function __invoke(): array
    {
        $servers = Server::query()->orderBy('name')->get();
        $snapshots = ServerSnapshot::query()->whereIn('server_id', $servers->modelKeys())
            ->whereIn('section', ['projects', 'sites'])->get(['server_id', 'section', 'data']);
        $section = fn (string $name) => $snapshots->where('section', $name)->pluck('data', 'server_id')->all();
        $links = ProjectFolder::query()->with(['project:id,name,client_id', 'project.client:id,legal_name,trade_name'])->get()
            ->mapWithKeys(fn (ProjectFolder $folder) => ["{$folder->server_id}|{$folder->path}" => [
                'project_id' => $folder->project_id, 'project_name' => $folder->project->name,
                'client_id' => $folder->project->client_id, 'client_name' => $folder->project->client->displayName(),
            ]])->all();

        return self::build($servers, $section('projects'), $section('sites'), $links);
    }

    /**
     * @param  iterable<Server>  $servers
     * @param  array<int, string|null>  $projects  the raw `projects` section by server id
     * @param  array<int, string|null>  $sites  the raw `sites` section by server id
     * @param  array<string, array<string, mixed>>  $links  by "server_id|path"
     * @return list<array<string, mixed>>
     */
    public static function build(iterable $servers, array $projects, array $sites, array $links = []): array
    {
        $rows = [];
        foreach ($servers as $server) {
            $data = json_decode($projects[$server->id] ?? '', true);
            if (! is_array($data)) {
                continue;
            }
            // Without sites the projects still list, just without domains.
            $vhosts = json_decode($sites[$server->id] ?? '', true)['items'] ?? [];
            foreach ($data['items'] ?? [] as $project) {
                $path = (string) ($project['path'] ?? '');
                $rows[] = array_filter([
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'server_status' => $server->displayStatus(),
                    'path' => $path,
                    'name' => (string) ($project['name'] ?? ''),
                    'type' => (string) ($project['type'] ?? ''),
                    'framework_version' => (string) ($project['framework_version'] ?? ''),
                    'branch' => (string) ($project['git']['branch'] ?? ''),
                    'domains' => self::domains($path, is_array($vhosts) ? $vhosts : []),
                    'subapps' => array_map(fn (array $subapp) => (string) ($subapp['path'] ?? ''), $project['subapps'] ?? []),
                    'linked' => $links["{$server->id}|{$path}"] ?? null,
                ], fn ($value, string $key) => ! in_array($key, ['framework_version', 'branch'], true) || $value !== '', ARRAY_FILTER_USE_BOTH);
            }
        }

        usort($rows, fn (array $a, array $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])) ?: strcmp($a['server_name'], $b['server_name']));

        return $rows;
    }

    /**
     * The names of every vhost serving the project or one of its subapps.
     *
     * @param  list<array<string, mixed>>  $vhosts
     * @return list<string>
     */
    private static function domains(string $path, array $vhosts): array
    {
        $domains = [];
        foreach ($vhosts as $site) {
            $served = array_filter($site['projects'] ?? [], fn ($served) => $served === $path || str_starts_with((string) $served, $path.'/'));
            if ($served === []) {
                continue;
            }
            foreach (SiteTargets::siteNames($site) as $name) {
                $domains[$name] = true;
            }
        }
        $domains = array_keys($domains);
        sort($domains, SORT_STRING);

        return $domains;
    }
}
