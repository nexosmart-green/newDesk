<?php

use App\Domain\Monitoring\Actions\AggregateHourlyMetrics;
use App\Domain\Monitoring\Actions\PurgeExpiredMetrics;
use App\Domain\Monitoring\Models\Server;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->freezeTime();
    $admin = provisionCompany();
    $this->server = tap((new Server)->forceFill([
        'company_id' => $admin->company_id, 'name' => 'reaquila', 'ip' => '203.0.113.10', 'agent_port' => 9100,
        'token' => 'agent-token-0123456789', 'tls_fingerprint' => str_repeat('a', 64),
    ]))->save();
    $this->rawPoint = fn ($ts, float $cpu) => DB::table('monitoring_metrics_raw')->insert([
        'company_id' => $admin->company_id, 'server_id' => $this->server->id, 'ts' => $ts,
        'cpu' => $cpu, 'memory' => 50, 'swap' => 10, 'disk' => 60, 'load1' => $cpu / 100, 'rx_bps' => 100, 'tx_bps' => 50,
    ]);
});

it('rolls an hour of raw samples into averages and maxima, idempotently', function () {
    $hour = now()->utc()->subHours(2)->startOfHour();
    ($this->rawPoint)($hour->copy()->addMinutes(1), 10);
    ($this->rawPoint)($hour->copy()->addMinutes(30), 30);
    ($this->rawPoint)($hour->copy()->addMinutes(70), 99); // next hour

    app(AggregateHourlyMetrics::class)($hour);
    $row = DB::table('monitoring_metrics_hourly')->sole();
    expect((float) $row->cpu)->toBe(20.0)
        ->and((float) $row->cpu_max)->toBe(30.0)
        ->and($row->samples)->toBe(2)
        ->and($row->company_id)->toBe($this->server->company_id);

    ($this->rawPoint)($hour->copy()->addMinutes(45), 50);
    app(AggregateHourlyMetrics::class)($hour);
    $row = DB::table('monitoring_metrics_hourly')->sole();
    expect((float) $row->cpu)->toBe(30.0)->and($row->samples)->toBe(3);
});

it('keeps raw samples 7 days, hourly rollups 1 year and site history 30 days', function () {
    ($this->rawPoint)(now()->subDays(8), 10);
    ($this->rawPoint)(now()->subDays(6), 10);
    $hourly = ['company_id' => $this->server->company_id, 'server_id' => $this->server->id, 'cpu' => 1, 'cpu_max' => 1, 'memory' => 1,
        'memory_max' => 1, 'swap' => 1, 'swap_max' => 1, 'disk' => 1, 'disk_max' => 1, 'load1' => 1, 'load1_max' => 1,
        'rx_bps' => 1, 'tx_bps' => 1, 'samples' => 1];
    DB::table('monitoring_metrics_hourly')->insert([
        ['hour' => now()->subDays(366)->startOfHour(), ...$hourly],
        ['hour' => now()->subDays(300)->startOfHour(), ...$hourly],
    ]);

    $check = ['company_id' => $this->server->company_id, 'server_id' => $this->server->id, 'server_name' => 'www.reaquila.com',
        'status_code' => 200, 'latency_ms' => 80, 'ok' => true];
    DB::table('monitoring_site_check_history')->insert([
        ['checked_at' => now()->subDays(31), ...$check],
        ['checked_at' => now()->subDays(29), ...$check],
    ]);

    expect(app(PurgeExpiredMetrics::class)(now()))
        ->toBe(['monitoring_metrics_raw' => 1, 'monitoring_metrics_hourly' => 1, 'monitoring_site_check_history' => 1,
            'status_checks' => 0, 'status_checks_hourly' => 0, 'status_periods' => 0, 'status_pagespeed_runs' => 0])
        ->and(DB::table('monitoring_metrics_raw')->count())->toBe(1)
        ->and(DB::table('monitoring_metrics_hourly')->count())->toBe(1)
        ->and(DB::table('monitoring_site_check_history')->count())->toBe(1);
});

it('aggregates the last three complete hours every hour from the scheduler', function () {
    foreach ([1, 2, 3, 4] as $hoursAgo) {
        ($this->rawPoint)(now()->utc()->subHours($hoursAgo)->startOfHour()->addMinutes(5), 10);
    }

    expect(Artisan::call('monitoring:retention'))->toBe(0)
        ->and(DB::table('monitoring_metrics_hourly')->count())->toBe(3);

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'monitoring:retention'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
