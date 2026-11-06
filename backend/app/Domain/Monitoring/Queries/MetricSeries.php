<?php

namespace App\Domain\Monitoring\Queries;

use App\Domain\Monitoring\Models\Server;
use Illuminate\Support\Facades\DB;

/**
 * The metric charts (RF-13.03): raw points up to 7 days, hourly rollups beyond
 * (monitoreo GET /api/servers/{id}/metrics).
 */
final class MetricSeries
{
    /** Seconds per range. */
    public const RANGES = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000, '1y' => 31536000];

    private const RAW_UP_TO = 604800;

    /** @return array{range: string, resolution: string, points: list<array<string, mixed>>} */
    public function __invoke(Server $server, string $range): array
    {
        $since = now()->utc()->subSeconds(self::RANGES[$range]);
        $raw = self::RANGES[$range] <= self::RAW_UP_TO;

        $rows = DB::table($raw ? 'monitoring_metrics_raw' : 'monitoring_metrics_hourly')
            ->where('server_id', $server->id)
            ->where($raw ? 'ts' : 'hour', '>=', $since)
            ->orderBy($raw ? 'ts' : 'hour')
            ->get();

        $points = $rows->map(function (object $row) use ($raw) {
            $point = ['ts' => str_replace(' ', 'T', $raw ? $row->ts : $row->hour).'Z', 'cpu' => (float) $row->cpu];
            if (! $raw) {
                $point['cpu_max'] = (float) $row->cpu_max;
            }
            foreach (['memory', 'swap', 'disk', 'load1', 'rx_bps', 'tx_bps'] as $column) {
                $point[$column] = (float) $row->{$column};
            }

            return $point;
        })->all();

        return ['range' => $range, 'resolution' => $raw ? 'raw' : 'hourly', 'points' => $points];
    }
}
