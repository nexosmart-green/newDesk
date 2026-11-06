<?php

namespace App\Domain\Support\Queries;

use App\Domain\Monitoring\Agent\MetricsSummary;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Queries\LinkedFolders;
use App\Domain\StatusPage\Queries\StatusPage;
use Carbon\CarbonImmutable;

/**
 * The servers a project lives on as they were when a client reported (monitoreo
 * TakeServerSnapshot, D-050): those of its linked folders and of its process/timer components, each
 * with its metrics, the project's own processes (running from its folders or named by its
 * components), the services and the open alerts. Team only; the client never sees it.
 */
final class ServerSnapshotAtReport
{
    /** @return array{taken_at: string, servers: list<array<string, mixed>>} */
    public function __invoke(Project $project, CarbonImmutable $now): array
    {
        $out = ['taken_at' => $now->utc()->toJSON(), 'servers' => []];
        $folders = ProjectFolder::query()->forCompany($project->company_id)->where('project_id', $project->id)->orderBy('id')->get();
        $components = StatusPage::components($project);
        $paths = [];
        $targets = [];
        foreach ($folders as $folder) {
            $paths[$folder->server_id][] = $folder->path;
        }
        foreach ($components as $component) {
            if ($component->server_id !== null && $component->target !== null) {
                $targets[$component->server_id][self::targetKey($component)] = true;
            }
        }
        $ids = $folders->pluck('server_id')->merge($components->pluck('server_id')->filter())->unique()->values()->all();
        if ($ids === []) {
            return $out;
        }

        $servers = Server::query()->forCompany($project->company_id)->whereIn('id', $ids)->get()->keyBy('id');
        $snapshots = ServerSnapshot::query()->forCompany($project->company_id)->whereIn('server_id', $ids)
            ->whereIn('section', ['metrics', 'pm2', 'supervisor', 'services'])->get()->groupBy('server_id');
        $alerts = Alert::query()->forCompany($project->company_id)->with('server')->where('state', Alert::FIRING)
            ->whereIn('server_id', $ids)->orderBy('first_seen_at')->get()->groupBy('server_id');

        foreach ($ids as $id) {
            $server = $servers->get($id);
            if ($server === null) {
                continue;
            }
            $sections = ($snapshots->get($id) ?? collect())->keyBy('section');
            $data = function (string $section) use ($sections): array {
                $raw = $sections->get($section)?->data;
                $decoded = $raw === null || $raw === '' ? null : json_decode($raw, true);

                return is_array($decoded) ? $decoded : [];
            };
            $ours = fn (string $name, string $alias, string ...$dirs) => isset($targets[$id][$name]) || ($alias !== '' && isset($targets[$id][$alias]))
                || collect($dirs)->contains(fn (string $dir) => $dir !== '' && collect($paths[$id] ?? [])->contains(fn (string $folder) => LinkedFolders::inside($dir, $folder)));

            $processes = [];
            foreach ($data('pm2')['daemons'] ?? [] as $daemon) {
                foreach ($daemon['processes'] ?? [] as $process) {
                    $name = (string) ($process['name'] ?? '');
                    if (($process['is_module'] ?? false) || ! $ours($name, $name.'@'.($daemon['user'] ?? ''), (string) ($process['cwd'] ?? ''))) {
                        continue;
                    }
                    $state = (string) ($process['status'] ?? '');
                    $processes[] = ['kind' => Component::PM2, 'name' => $name, 'state' => $state, 'healthy' => $state === 'online',
                        'restarts' => (int) ($process['restarts'] ?? 0), 'cpu_percent' => (float) ($process['cpu_percent'] ?? 0),
                        'memory_bytes' => (int) ($process['memory_bytes'] ?? 0)];
                }
            }
            foreach ($data('supervisor')['programs'] ?? [] as $program) {
                $name = (string) ($program['name'] ?? '');
                if ($ours($name, (string) ($program['group'] ?? ''), (string) ($program['project'] ?? ''), (string) ($program['directory'] ?? ''))) {
                    $processes[] = ['kind' => Component::SUPERVISOR, 'name' => $name, 'state' => (string) ($program['state'] ?? ''),
                        'healthy' => (bool) ($program['healthy'] ?? false)];
                }
            }

            $out['servers'][] = [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status,
                'last_seen_at' => $server->last_seen_at?->toJSON(),
                'metrics' => MetricsSummary::fromSnapshot($sections->get('metrics')),
                'processes' => $processes,
                'services' => array_map(fn (array $service) => ['name' => (string) ($service['name'] ?? ''), 'active' => (string) ($service['active'] ?? ''),
                    'healthy' => (bool) ($service['healthy'] ?? false)], $data('services')['items'] ?? []),
                'alerts' => ($alerts->get($id) ?? collect())->map(fn (Alert $alert) => $alert->toPayload())->values()->all(),
            ];
        }
        usort($out['servers'], fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /** A PM2 app bound to a user only matches in that user's daemon (D-089). */
    private static function targetKey(Component $component): string
    {
        return $component->kind === Component::PM2 && (string) $component->target_owner !== ''
            ? "{$component->target}@{$component->target_owner}"
            : $component->target;
    }
}
