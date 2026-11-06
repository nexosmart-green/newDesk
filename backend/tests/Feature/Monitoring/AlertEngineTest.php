<?php

use App\Domain\Monitoring\Actions\ApplyAlertResults;
use App\Domain\Monitoring\Alerts\AlertObservation;
use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Alerts\AlertState;
use App\Domain\Monitoring\Events\AlertBroadcast;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Port of monitoreo alerts/alerts_test.go and portal/flood_test.go: the engine against the real
 * database, its streaks in the cache. What monitoreo's OnChange heard is what AlertChanged carries.
 */
beforeEach(function () {
    Event::fake([AlertChanged::class, AlertBroadcast::class]);
    $admin = provisionCompany();
    $this->server = tap((new Server)->forceFill([
        'company_id' => $admin->company_id, 'name' => 'reaquila', 'ip' => '203.0.113.10', 'agent_port' => 9100,
        'token' => 'agent-token-0123456789', 'tls_fingerprint' => str_repeat('a', 64),
    ]))->save();
    $this->apply = fn (array $results, CarbonImmutable $at) => app(ApplyAlertResults::class)($this->server, $results, $at);
    $this->rules = app(AlertRules::class);
    $this->start = CarbonImmutable::parse('2026-09-30 12:00:00', 'UTC');
});

/** "change severity message" of every AlertChanged so far, as monitoreo's test recorder. @return list<string> */
function alertChanges(): array
{
    return Event::dispatched(AlertChanged::class)
        ->map(fn (array $dispatch) => "{$dispatch[0]->change} {$dispatch[0]->alert['severity']} {$dispatch[0]->alert['message']}")
        ->values()->all();
}

function firingAlerts(Server $server, string $rule): int
{
    return Alert::query()->where('server_id', $server->id)->where('rule', $rule)->where('state', 'firing')->count();
}

it('opens after two polls 90 s apart, never reminds, and resolves (D-024)', function () {
    $supervisor = fn (string $state) => $this->rules->forSection($this->server->id, 'supervisor', ['available' => true, 'programs' => [
        ['name' => 'tfp-api-horizon', 'state' => $state, 'healthy' => $state === 'RUNNING'],
    ]], $this->start);

    ($this->apply)($supervisor('FATAL'), $this->start->subMinutes(2));
    expect(firingAlerts($this->server, AlertRules::SUPERVISOR))->toBe(0)->and(alertChanges())->toBe([]);

    ($this->apply)($supervisor('FATAL'), $this->start);
    expect(firingAlerts($this->server, AlertRules::SUPERVISOR))->toBe(1)
        ->and(alertChanges())->toBe(['opened critical Supervisor: tfp-api-horizon en estado FATAL']);

    // Still firing hours later: no reminders.
    ($this->apply)($supervisor('FATAL'), $this->start->addHour());
    ($this->apply)($supervisor('FATAL'), $this->start->addHours(6)->addMinute());
    expect(alertChanges())->toHaveCount(1)
        ->and(Alert::query()->sole()->last_seen_at->toDateTimeString())->toBe('2026-09-30 18:01:00');

    ($this->apply)($supervisor('RUNNING'), $this->start->addHours(7));
    $alert = Alert::query()->sole();
    expect(firingAlerts($this->server, AlertRules::SUPERVISOR))->toBe(0)
        ->and(alertChanges()[1])->toStartWith('resolved')
        ->and($alert->state)->toBe('resolved')
        ->and($alert->resolved_at->toDateTimeString())->toBe('2026-09-30 19:00:00')
        ->and($alert->first_seen_at->toDateTimeString())->toBe('2026-09-30 12:00:00');

    // A program that disappears resolves its alert.
    ($this->apply)($supervisor('BACKOFF'), $this->start->addHours(8));
    ($this->apply)($supervisor('BACKOFF'), $this->start->addHours(8)->addMinutes(2));
    expect(firingAlerts($this->server, AlertRules::SUPERVISOR))->toBe(1);
    ($this->apply)($this->rules->forSection($this->server->id, 'supervisor', ['available' => true], $this->start), $this->start->addHours(9));
    expect(firingAlerts($this->server, AlertRules::SUPERVISOR))->toBe(0);
});

it('needs five minutes above the threshold for a CPU alert', function () {
    $metrics = fn () => $this->rules->forSection($this->server->id, 'metrics', ['cpu' => ['usage_percent' => 97]], $this->start);

    foreach (range(0, 4) as $minute) {
        ($this->apply)($metrics(), $this->start->addMinutes($minute));
    }
    expect(firingAlerts($this->server, AlertRules::CPU))->toBe(0);

    ($this->apply)($metrics(), $this->start->addMinutes(5));
    expect(firingAlerts($this->server, AlertRules::CPU))->toBe(1);
});

it('does not alert on a mass restart, only on a unit that stays down (D-024)', function () {
    $services = fn (bool $mysqlUp) => $this->rules->forSection($this->server->id, 'services', ['items' => [
        ['name' => 'nginx', 'active' => 'active', 'healthy' => true],
        ['name' => 'mysql', 'active' => $mysqlUp ? 'active' : 'activating', 'healthy' => $mysqlUp],
    ]], $this->start);
    $start = CarbonImmutable::parse('2026-09-30 06:29:00', 'UTC');

    ($this->apply)($services(false), $start);
    ($this->apply)($services(true), $start->addMinute());
    expect(firingAlerts($this->server, AlertRules::SERVICE))->toBe(0)->and(alertChanges())->toBe([]);

    // Two quick polls (a manual refresh) inside the grace do not alert either.
    ($this->apply)($services(false), $start->addMinutes(10));
    ($this->apply)($services(false), $start->addMinutes(10)->addSeconds(10));
    expect(firingAlerts($this->server, AlertRules::SERVICE))->toBe(0);

    ($this->apply)($services(false), $start->addMinutes(12));
    expect(firingAlerts($this->server, AlertRules::SERVICE))->toBe(1)->and(alertChanges())->toHaveCount(1);
});

function diskResult(float $percent): AlertResult
{
    return new AlertResult(AlertRules::DISK, 0, 1, [new AlertObservation(
        '/', $percent >= AlertRules::DISK_WARNING, $percent >= AlertRules::DISK_CRITICAL ? 'critical' : 'warning', 'Disco / alto', (string) $percent,
    )]);
}

it('reports a warning that turns critical once', function () {
    foreach ([88, 96, 97, 50] as $minute => $percent) {
        ($this->apply)([diskResult($percent)], $this->start->addMinutes($minute));
    }
    $changes = alertChanges();

    expect($changes)->toHaveCount(3)
        ->and($changes[0])->toBe('opened warning Disco / alto')
        ->and($changes[1])->toBe('escalated critical Disco / alto')
        ->and($changes[2])->toStartWith('resolved');
});

it('broadcasts the opened and resolved alert as monitoreo SSE `alert` did', function () {
    ($this->apply)([diskResult(96)], $this->start);
    ($this->apply)([diskResult(97)], $this->start->addMinute());
    ($this->apply)([diskResult(10)], $this->start->addMinutes(2));

    // Sent apart from AlertChanged: Reverb being down never stops a notice.
    $events = Event::dispatched(AlertBroadcast::class)->map(fn (array $dispatch) => $dispatch[0])->values();
    $alert = Alert::query()->sole();
    expect($events->map(fn (AlertBroadcast $event) => [$event->change->change, $event->broadcastWhen()])->all())
        ->toBe([['opened', true], ['resolved', true]])
        ->and($events[0]->broadcastAs())->toBe('alert')
        ->and($events[0]->broadcastOn()->name)->toBe("private-monitoring.{$this->server->company_id}")
        ->and($events[0]->broadcastWith())->toBe([
            'id' => $alert->id, 'server_id' => $this->server->id, 'server_name' => 'reaquila', 'rule' => 'disk_usage',
            'target' => '/', 'severity' => 'critical', 'state' => 'firing', 'message' => 'Disco / alto', 'value' => '96',
            'first_seen_at' => '2026-09-30T12:00:00Z', 'last_seen_at' => '2026-09-30T12:00:00Z', 'resolved_at' => null, 'last_notified_at' => null,
        ])
        ->and($events[1]->broadcastWith()['state'])->toBe('resolved')
        ->and($events[1]->broadcastWith()['resolved_at'])->toBe('2026-09-30T12:02:00Z')
        ->and($events[1]->broadcastWith()['value'])->toBe('97');
});

/** @return array{AlertResult, AlertResult} 30 PM2 processes of one user failing, then healthy */
function pm2Flood(string $user, string $severity = 'critical'): array
{
    $failing = [];
    $healthy = [];
    foreach (range(0, 29) as $i) {
        $target = sprintf('w_%02d#%d@%s', $i, $i, $user);
        $failing[] = new AlertObservation($target, true, $severity, "PM2 {$target} en estado errored", 'errored');
        $healthy[] = new AlertObservation($target, false, 'critical', '', 'online');
    }

    return [new AlertResult(AlertRules::PM2_STATUS, 0, 1, $failing), new AlertResult(AlertRules::PM2_STATUS, 0, 1, $healthy)];
}

it('summarizes a flood of openings on one server instead of one notice each (D-107, CA-13.11)', function () {
    [$failing, $healthy] = pm2Flood('deploy');
    ($this->apply)([$failing], $this->start);

    $events = Event::dispatched(AlertChanged::class)->map(fn (array $dispatch) => $dispatch[0])->values();
    $opened = $events->where('change', 'opened');
    $summary = $events->firstWhere('change', 'summary');
    expect(firingAlerts($this->server, AlertRules::PM2_STATUS))->toBe(30) // every alert is still stored
        ->and($opened)->toHaveCount(30)
        ->and($opened->every(fn (AlertChanged $event) => $event->summarized && (new AlertBroadcast($event))->broadcastWhen()))->toBeTrue()
        ->and((new AlertBroadcast($summary))->broadcastWhen())->toBeFalse()
        ->and($summary->alert['id'])->toBeNull()
        ->and($summary->alert['severity'])->toBe('critical')
        ->and($summary->alert['value'])->toBe('30')
        ->and($summary->alert['message'])->toBe('30 alertas pm2_process nuevas a la vez: w_00#0@deploy, w_01#1@deploy, w_02#2@deploy, w_03#3@deploy, w_04#4@deploy y 25 más');

    ($this->apply)([$healthy], $this->start->addMinute());
    expect(firingAlerts($this->server, AlertRules::PM2_STATUS))->toBe(0)
        ->and(Event::dispatched(AlertChanged::class)->filter(fn (array $dispatch) => $dispatch[0]->change === 'resolved'))->toHaveCount(30);
});

it('summarizes per daemon user, so a flood does not hide another user\'s alert', function () {
    [$flood] = pm2Flood('intruso', 'warning');
    $result = new AlertResult(AlertRules::PM2_STATUS, 0, 1, [
        ...$flood->observations,
        new AlertObservation('otro-api#3@deploy', true, 'critical', 'PM2 otro-api en estado errored', 'errored'),
    ]);
    ($this->apply)([$result], $this->start);

    $events = Event::dispatched(AlertChanged::class)->map(fn (array $dispatch) => $dispatch[0])->values();
    $notices = $events->reject(fn (AlertChanged $event) => $event->summarized)->values();
    expect($notices)->toHaveCount(2)
        ->and($notices[0]->change)->toBe('summary')
        ->and($notices[0]->alert['severity'])->toBe('warning')
        ->and($notices[0]->alert['message'])->toStartWith('30 alertas pm2_process')
        ->and($notices[1]->change)->toBe('opened')
        ->and($notices[1]->alert['target'])->toBe('otro-api#3@deploy');
});

it('keeps its streaks in the cache and forgets a deleted server', function () {
    $state = app(AlertState::class);
    $two = new AlertResult(AlertRules::DISK, 0, 2, diskResult(96)->observations);

    ($this->apply)([$two], $this->start);
    expect($state->streaks($this->server->id, AlertRules::DISK))->toBe(['/' => ['since' => $this->start->getTimestamp(), 'count' => 1]])
        // The per server+rule lock is free again.
        ->and(Cache::lock(AlertState::lockKey($this->server->id, AlertRules::DISK), 1)->get())->toBeTrue();

    $state->forgetServer($this->server->id);
    ($this->apply)([$two], $this->start->addMinute());
    expect(firingAlerts($this->server, AlertRules::DISK))->toBe(0); // the streak started over

    ($this->apply)([$two], $this->start->addMinutes(2));
    expect(firingAlerts($this->server, AlertRules::DISK))->toBe(1)
        ->and($state->streaks($this->server->id, AlertRules::DISK)['/']['count'])->toBe(2);

    ($this->apply)([diskResult(50)], $this->start->addMinutes(3));
    expect($state->streaks($this->server->id, AlertRules::DISK))->toBe([]);
});
