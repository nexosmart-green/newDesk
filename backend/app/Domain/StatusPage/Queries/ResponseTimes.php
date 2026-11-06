<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Status\Parts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How fast the client's web, app and CMS answer (D-042, monitoreo portal.ResponseTimes): 5-minute
 * buckets of the raw probes for 24 h, the hourly rollup beyond. The API is behind them: only http
 * components with parts.
 */
final class ResponseTimes
{
    public const RANGES = ['24h' => 86400, '7d' => 604800, '30d' => 2592000];

    /** @return array{range: string, resolution: string, series: list<array{label: string, kind: string, points: list<array{ts: string, avg_ms: int, max_ms: int}>}>} */
    public function __invoke(Project $project, string $range): array
    {
        $series = [];
        foreach (StatusPage::components($project) as $component) {
            if ($component->kind === Component::HTTP && $component->hasParts()) {
                $series[$component->id] = ['label' => Parts::label($component->parts), 'kind' => $component->parts[0], 'points' => []];
            }
        }
        $since = CarbonImmutable::now()->utc()->subSeconds(self::RANGES[$range]);

        if ($range !== '24h') {
            $hours = DB::table('status_checks_hourly')->whereIn('component_id', array_keys($series))->where('hour', '>=', $since)
                ->whereNotNull('avg_ms')->whereNotNull('max_ms') // every probe of the hour failed: the gap shows it
                ->orderBy('hour')->get();
            foreach ($hours as $hour) {
                $series[$hour->component_id]['points'][] = ['ts' => self::iso($hour->hour), 'avg_ms' => (int) round((float) $hour->avg_ms), 'max_ms' => (int) $hour->max_ms];
            }

            return ['range' => $range, 'resolution' => '1h', 'series' => array_values($series)];
        }

        $buckets = [];
        $checks = DB::table('status_checks')->whereIn('component_id', array_keys($series))->where('checked_at', '>=', $since)
            ->whereNotNull('latency_ms')->orderBy('checked_at')->get(['component_id', 'checked_at', 'latency_ms']);
        foreach ($checks as $check) {
            $at = intdiv(CarbonImmutable::parse($check->checked_at, 'UTC')->getTimestamp(), 300) * 300;
            $bucket = $buckets[$check->component_id][$at] ?? ['sum' => 0, 'count' => 0, 'max' => 0];
            $buckets[$check->component_id][$at] = ['sum' => $bucket['sum'] + $check->latency_ms, 'count' => $bucket['count'] + 1, 'max' => max($bucket['max'], $check->latency_ms)];
        }
        foreach ($buckets as $componentId => $perBucket) {
            foreach ($perBucket as $at => $bucket) { // checks come sorted: chronological
                $series[$componentId]['points'][] = [
                    'ts' => CarbonImmutable::createFromTimestampUTC($at)->toIso8601ZuluString(),
                    'avg_ms' => intdiv($bucket['sum'], $bucket['count']),
                    'max_ms' => $bucket['max'],
                ];
            }
        }

        return ['range' => $range, 'resolution' => '5m', 'series' => array_values($series)];
    }

    private static function iso(string $datetime): string
    {
        return CarbonImmutable::parse($datetime, 'UTC')->toIso8601ZuluString();
    }
}
