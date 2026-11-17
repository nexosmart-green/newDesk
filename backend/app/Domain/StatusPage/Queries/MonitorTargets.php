<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Monitoring\Models\Server;

/**
 * GET portal-admin/targets: what each enabled server can be watched for, from its snapshots. A PM2
 * app goes with the user whose daemon runs it (D-089); a numprocs group is watched as one; only our
 * own timers (backups), not the distro's.
 */
final class MonitorTargets
{
    /** @return list<array{server_id: int, server_name: string, pm2: list<array{name: string, owner: string}>, supervisor: list<string>, timers: list<string>}> */
    public function __invoke(): array
    {
        $servers = Server::query()->where('enabled', true)->orderBy('name')->get(['id', 'name']);
        $sections = LinkedFolders::sections($servers->modelKeys(), ['pm2', 'supervisor', 'cron']);

        return $servers->map(function (Server $server) use ($sections) {
            $pm2 = [];
            foreach ($sections['pm2'][$server->id]['daemons'] ?? [] as $daemon) {
                foreach ($daemon['processes'] ?? [] as $process) {
                    if (! ($process['is_module'] ?? false)) {
                        $pm2[($process['name'] ?? '')."\0".($daemon['user'] ?? '')] = ['name' => (string) ($process['name'] ?? ''), 'owner' => (string) ($daemon['user'] ?? '')];
                    }
                }
            }
            ksort($pm2, SORT_STRING);
            $supervisor = array_map(fn (array $program) => (string) (($program['group'] ?? '') !== '' ? $program['group'] : ($program['name'] ?? '')),
                $sections['supervisor'][$server->id]['programs'] ?? []);
            $timers = array_map(fn (array $timer) => (string) ($timer['unit'] ?? ''),
                array_filter($sections['cron'][$server->id]['timers'] ?? [], fn (array $timer) => $timer['custom'] ?? false));

            return ['server_id' => $server->id, 'server_name' => $server->name, 'pm2' => array_values($pm2),
                'supervisor' => self::sortedUnique($supervisor), 'timers' => self::sortedUnique($timers)];
        })->all();
    }

    /** @param  list<string>  $names @return list<string> */
    private static function sortedUnique(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }
}
