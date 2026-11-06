<?php

namespace App\Domain\Monitoring\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rolls one complete hour of raw samples into monitoring_metrics_hourly (the 30d/1y charts), and of
 * status page probes into status_checks_hourly (the 7d/30d response times, RF-13.06), as monitoreo's
 * AggregateHour. Idempotent: running it again for the same hour overwrites the rows.
 */
final class AggregateHourlyMetrics
{
    public function __invoke(CarbonInterface $hour): void
    {
        $start = $hour->toImmutable()->utc()->startOfHour();

        DB::statement(<<<'SQL'
            INSERT INTO monitoring_metrics_hourly (server_id, hour, company_id, cpu, cpu_max, memory, memory_max,
                swap, swap_max, disk, disk_max, load1, load1_max, rx_bps, tx_bps, samples)
            SELECT server_id, ?, company_id, AVG(cpu), MAX(cpu), AVG(memory), MAX(memory), AVG(swap), MAX(swap),
                AVG(disk), MAX(disk), AVG(load1), MAX(load1), AVG(rx_bps), AVG(tx_bps), COUNT(*)
            FROM monitoring_metrics_raw WHERE ts >= ? AND ts < ? GROUP BY server_id, company_id
            ON DUPLICATE KEY UPDATE cpu = VALUES(cpu), cpu_max = VALUES(cpu_max), memory = VALUES(memory),
                memory_max = VALUES(memory_max), swap = VALUES(swap), swap_max = VALUES(swap_max), disk = VALUES(disk),
                disk_max = VALUES(disk_max), load1 = VALUES(load1), load1_max = VALUES(load1_max),
                rx_bps = VALUES(rx_bps), tx_bps = VALUES(tx_bps), samples = VALUES(samples)
            SQL, [$start, $start, $start->addHour()]);

        DB::statement(<<<'SQL'
            INSERT INTO status_checks_hourly (component_id, hour, company_id, samples, failures, avg_ms, max_ms)
            SELECT component_id, ?, company_id, COUNT(*), SUM(ok = 0), AVG(latency_ms), MAX(latency_ms)
            FROM status_checks WHERE checked_at >= ? AND checked_at < ? GROUP BY component_id, company_id
            ON DUPLICATE KEY UPDATE samples = VALUES(samples), failures = VALUES(failures), avg_ms = VALUES(avg_ms),
                max_ms = VALUES(max_ms)
            SQL, [$start, $start, $start->addHour()]);
    }
}
