<?php

namespace App\Domain\Monitoring\Agent;

use App\Domain\Monitoring\Models\ServerSnapshot;
use Carbon\CarbonInterface;

/**
 * ServerSummary.metrics of monitoreo (view.Summarize), shared by the API and the realtime events.
 */
final class MetricsSummary
{
    /** @return array{cpu_percent: float, memory_percent: float, swap_percent: float, disk_percent: float, load1: float, uptime_seconds: int, collected_at: string} */
    public static function from(mixed $metrics, CarbonInterface $collectedAt): array
    {
        $disk = 0.0;
        foreach ((array) ($metrics->disks ?? []) as $mount) {
            $disk = max($disk, (float) ($mount->used_percent ?? 0));
        }

        return [
            'cpu_percent' => (float) ($metrics->cpu->usage_percent ?? 0),
            'memory_percent' => (float) ($metrics->memory->used_percent ?? 0),
            'swap_percent' => (float) ($metrics->swap->used_percent ?? 0),
            'disk_percent' => $disk,
            'load1' => (float) ($metrics->cpu->load1 ?? 0),
            'uptime_seconds' => (int) ($metrics->uptime_seconds ?? 0),
            'collected_at' => $collectedAt->toImmutable()->utc()->toIso8601ZuluString(),
        ];
    }

    /** Null when there is no usable metrics answer yet. @return array<string, mixed>|null */
    public static function fromSnapshot(?ServerSnapshot $snapshot): ?array
    {
        if ($snapshot === null || $snapshot->collected_at === null || $snapshot->data === null || $snapshot->data === '') {
            return null;
        }
        $metrics = json_decode($snapshot->data);

        return is_object($metrics) ? self::from($metrics, $snapshot->collected_at) : null;
    }
}
