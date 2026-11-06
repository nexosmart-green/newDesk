<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Monitoring\Models\Server;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\ProjectFolder;

/**
 * Dependency vulnerabilities from security-scan.sh (D-053): the last scan of every server, each
 * audited folder given to the linked folder of the same server with the longest path that equals
 * or contains it.
 */
final class Vulnerabilities
{
    private const ZERO = ['critical' => 0, 'high' => 0, 'moderate' => 0, 'low' => 0];

    /** @var array<int, list<array<string, mixed>>> audited folders by project id */
    public array $byProject = [];

    /** @var list<array<string, mixed>> folders no client project links */
    public array $unassigned = [];

    /** @var array<int, string|null> servers whose agent sent a scan (null: no scan file) */
    public array $scannedAt = [];

    /** Every server's last scan. */
    public static function load(): self
    {
        $scans = array_filter(LinkedFolders::sections(null, ['vulnerabilities'])['vulnerabilities'], fn (array $scan) => $scan !== []);
        $folders = ProjectFolder::query()->get(['project_id', 'server_id', 'path'])
            ->map(fn (ProjectFolder $folder) => $folder->only(['project_id', 'server_id', 'path']))->all();

        return self::assign($scans, $folders, Server::query()->pluck('name', 'id')->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans  the `vulnerabilities` section by server id
     * @param  list<array{project_id: int, server_id: int, path: string}>  $folders  every linked folder
     * @param  array<int, string>  $serverNames
     */
    public static function assign(array $scans, array $folders, array $serverNames): self
    {
        $out = new self;
        foreach ($scans as $serverId => $scan) {
            $out->scannedAt[$serverId] = $scan['scanned_at'] ?? null;
            foreach ($scan['items'] ?? [] as $dir) {
                $view = ['server_id' => $serverId, 'server_name' => $serverNames[$serverId] ?? '', 'scanned_at' => $scan['scanned_at'] ?? null, ...$dir];
                [$owner, $longest] = [null, -1];
                foreach ($folders as $folder) {
                    if ($folder['server_id'] !== $serverId || strlen($folder['path']) <= $longest) {
                        continue;
                    }
                    $path = (string) ($dir['path'] ?? '');
                    if ($path === $folder['path'] || str_starts_with($path, rtrim($folder['path'], '/').'/')) {
                        [$owner, $longest] = [$folder['project_id'], strlen($folder['path'])];
                    }
                }
                if ($owner === null) {
                    $out->unassigned[] = $view;
                } else {
                    $out->byProject[$owner][] = $view;
                }
            }
        }
        $out->byProject = array_map(self::sorted(...), $out->byProject);
        $out->unassigned = self::sorted($out->unassigned);

        return $out;
    }

    /** The findings of a project's folders added up. @return array{critical: int, high: int, moderate: int, low: int} */
    public function counts(int $projectId): array
    {
        $total = self::ZERO;
        foreach ($this->byProject[$projectId] ?? [] as $dir) {
            foreach (self::ZERO as $severity => $_) {
                $total[$severity] += (int) ($dir['counts'][$severity] ?? 0);
            }
        }

        return $total;
    }

    /** GET portal-admin/projects/{id}/vulnerabilities. @return array<string, mixed> */
    public function forProject(Project $project): array
    {
        $servers = [];
        $folders = ProjectFolder::query()->with('server:id,name')->where('project_id', $project->id)->get()
            ->sortBy([fn ($a, $b) => strcmp($a->server->name, $b->server->name), fn ($a, $b) => strcmp($a->path, $b->path)]);
        foreach ($folders as $folder) {
            if (isset($servers[$folder->server_id])) {
                continue;
            }
            $scannedAt = $this->scannedAt[$folder->server_id] ?? null;
            $servers[$folder->server_id] = [
                'server_id' => $folder->server_id, 'server_name' => $folder->server->name,
                'has_scan' => array_key_exists($folder->server_id, $this->scannedAt) && $scannedAt !== null, 'scanned_at' => $scannedAt,
            ];
        }

        return ['counts' => $this->counts($project->id), 'servers' => array_values($servers), 'dirs' => $this->byProject[$project->id] ?? []];
    }

    /** By server name, path and manager. @param  list<array<string, mixed>>  $dirs @return list<array<string, mixed>> */
    private static function sorted(array $dirs): array
    {
        usort($dirs, fn (array $a, array $b) => strcmp($a['server_name'], $b['server_name'])
            ?: strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? '')) ?: strcmp((string) ($a['manager'] ?? ''), (string) ($b['manager'] ?? '')));

        return $dirs;
    }
}
