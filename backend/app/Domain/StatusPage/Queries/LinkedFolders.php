<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\ProjectFolder;
use Carbon\CarbonImmutable;

/**
 * The inventory folders linked to a project (D-044), resolved against the latest `projects`
 * snapshot of their server: name, types (D-072, D-123) and last deploy (D-042).
 */
final class LinkedFolders
{
    /**
     * monitoreo's FolderView list, by server name and path.
     *
     * @return list<array{server_id: int, server_name: string, path: string, name: string, types: list<string>, last_update_at: string|null}>
     */
    public function forProject(Project $project): array
    {
        $folders = ProjectFolder::query()->with('server:id,name')->where('project_id', $project->id)->get()
            ->sortBy([fn ($a, $b) => strcmp($a->server->name, $b->server->name), fn ($a, $b) => strcmp($a->path, $b->path)])->values();
        if ($folders->isEmpty()) {
            return [];
        }
        $serverIds = $folders->pluck('server_id')->unique()->all();
        $inventories = self::sections($serverIds, ['projects'])['projects'];
        $inUse = null; // loaded only for folders with subapps

        $views = [];
        foreach ($folders as $folder) {
            $view = ['server_id' => $folder->server_id, 'server_name' => $folder->server->name, 'path' => $folder->path,
                'name' => basename($folder->path), 'types' => [], 'last_update_at' => null];
            foreach ($inventories[$folder->server_id]['items'] ?? [] as $item) {
                if (($item['path'] ?? null) !== $folder->path) {
                    continue;
                }
                $view['name'] = (string) ($item['name'] ?? '');
                $view['types'][] = (string) ($item['type'] ?? '');
                if (($item['subapps'] ?? []) !== []) {
                    $inUse ??= self::pathsInUse($serverIds);
                }
                foreach (self::servedSubappTypes($item, $inUse[$folder->server_id] ?? []) as $type) {
                    if (! in_array($type, $view['types'], true)) {
                        $view['types'][] = $type;
                    }
                }
                $deployed = $item['git']['deployed_at'] ?? $item['git']['commit_date'] ?? null;
                $view['last_update_at'] = $deployed === null ? null : CarbonImmutable::parse($deployed)->utc()->toIso8601ZuluString();
            }
            $views[] = $view;
        }

        return $views;
    }

    /** The client's "última actualización": only the date, never a folder name. @return array{at: string}|null */
    public static function latest(array $views): ?array
    {
        $dates = array_filter(array_column($views, 'last_update_at'));
        usort($dates, fn (string $a, string $b) => CarbonImmutable::parse($b)->getTimestamp() <=> CarbonImmutable::parse($a)->getTimestamp());

        return $dates === [] ? null : ['at' => $dates[0]];
    }

    public static function inside(string $candidate, string $folder): bool
    {
        return $candidate === $folder || str_starts_with($candidate, $folder.'/');
    }

    /**
     * The decoded latest snapshot of each section, by server id.
     *
     * @param  list<int>|null  $serverIds  null: every server
     * @param  list<string>  $sections
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function sections(?array $serverIds, array $sections): array
    {
        $out = array_fill_keys($sections, []);
        ServerSnapshot::query()->whereIn('section', $sections)
            ->when($serverIds !== null, fn ($query) => $query->whereIn('server_id', $serverIds))
            ->get(['server_id', 'section', 'data'])
            ->each(function (ServerSnapshot $snapshot) use (&$out) {
                $data = json_decode((string) $snapshot->data, true);
                $out[$snapshot->section][$snapshot->server_id] = is_array($data) ? $data : [];
            });

        return $out;
    }

    /**
     * The types of the subapps a vhost serves or a PM2 app or Supervisor program runs from: the same
     * monorepo cloned on two servers has every subapp on both, but each runs only its part. Without
     * any such signal every subapp counts (D-123).
     *
     * @param  array<string, mixed>  $item  one project of the inventory
     * @param  list<string>  $inUse
     * @return list<string>
     */
    private static function servedSubappTypes(array $item, array $inUse): array
    {
        [$served, $all] = [[], []];
        foreach ($item['subapps'] ?? [] as $subapp) {
            $type = (string) ($subapp['type'] ?? '');
            $all[] = $type;
            $folder = $item['path'].'/'.($subapp['path'] ?? '');
            foreach ($inUse as $used) {
                if (self::inside((string) $used, $folder)) {
                    $served[] = $type;
                    break;
                }
            }
        }

        return $served ?: $all;
    }

    /**
     * Per server, the folders its vhosts serve and its PM2 apps and Supervisor programs run from.
     *
     * @param  list<int>  $serverIds
     * @return array<int, list<string>>
     */
    private static function pathsInUse(array $serverIds): array
    {
        $sections = self::sections($serverIds, ['sites', 'pm2', 'supervisor']);
        $out = [];
        foreach ($sections['sites'] as $serverId => $sites) {
            foreach ($sites['items'] ?? [] as $site) {
                foreach ($site['projects'] ?? [] as $served) {
                    $out[$serverId][] = (string) $served;
                }
            }
        }
        foreach ($sections['pm2'] as $serverId => $pm2) {
            foreach ($pm2['daemons'] ?? [] as $daemon) {
                foreach ($daemon['processes'] ?? [] as $process) {
                    if (! ($process['is_module'] ?? false)) {
                        $out[$serverId][] = (string) ($process['cwd'] ?? '');
                    }
                }
            }
        }
        foreach ($sections['supervisor'] as $serverId => $supervisor) {
            foreach ($supervisor['programs'] ?? [] as $program) {
                $out[$serverId][] = (string) ($program['project'] ?? '');
                $out[$serverId][] = (string) ($program['directory'] ?? '');
            }
        }

        return $out;
    }
}
