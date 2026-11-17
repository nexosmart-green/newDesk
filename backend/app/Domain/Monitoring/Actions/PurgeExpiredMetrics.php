<?php

namespace App\Domain\Monitoring\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Retention as in monitoreo: raw samples 7 days, hourly rollups 1 year, site check history 30 days;
 * status page probes 7 days, their hourly rollups, closed periods and PageSpeed runs 400 days (an
 * open period is the current state and stays, D-124). Alerts are kept.
 */
final class PurgeExpiredMetrics
{
    /** @return array<string, int> deleted rows per table */
    public function __invoke(CarbonInterface $now): array
    {
        $now = $now->toImmutable()->utc();
        $jobs = [
            'monitoring_metrics_raw' => ['ts', $now->subDays(7)],
            'monitoring_metrics_hourly' => ['hour', $now->subYear()],
            'monitoring_site_check_history' => ['checked_at', $now->subDays(30)],
            'status_checks' => ['checked_at', $now->subDays(7)],
            'status_checks_hourly' => ['hour', $now->subDays(400)],
            'status_periods' => ['ended_at', $now->subDays(400)],
            'status_pagespeed_runs' => ['measured_at', $now->subDays(400)],
        ];

        $deleted = [];
        foreach ($jobs as $table => [$column, $before]) {
            $deleted[$table] = 0;
            // Batched so a large backlog never holds long locks.
            do {
                $count = DB::table($table)->where($column, '<', $before)->limit(5000)->delete();
                $deleted[$table] += $count;
            } while ($count === 5000);
        }

        return $deleted;
    }
}
