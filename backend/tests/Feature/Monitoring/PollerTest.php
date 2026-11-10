<?php

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Alerts\AlertState;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Events\MetricsCollected;
use App\Domain\Monitoring\Events\ServerStatusChanged;
use App\Domain\Monitoring\Events\SnapshotSaved;
use App\Domain\Monitoring\Events\VulnerabilitiesScanned;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Polling\Poller;
use App\Domain\Monitoring\Polling\PollerSignals;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeAgentGateway;

/**
 * Port of monitoreo's poller_test.go: a fake agent, a fake clock (travel) and the real database.
 */
beforeEach(function () {
    Event::fake([ServerStatusChanged::class, SnapshotSaved::class, MetricsCollected::class]);
    $this->freezeTime();
    $this->agent = new FakeAgentGateway;
    $this->agent->sections = [
        'health' => ['status' => 'ok', 'version' => 'v-test', 'hostname' => 'mail.reaquila.com'],
        'system' => ['hostname' => 'mail.reaquila.com', 'ssh_port' => 1080, 'os' => ['pretty_name' => 'Ubuntu 24.10', 'eol' => true]],
        'metrics' => FakeAgentGateway::metricsWithDisk(65),
    ];
    $this->app->instance(AgentGateway::class, $this->agent);
    $this->admin = adminUser();
    $this->server = pollerServer($this->admin);
    $this->poller = app(Poller::class);
});

function pollerServer(User $admin, array $attributes = []): Server
{
    return tap((new Server)->forceFill([
        'company_id' => $admin->company_id,
        'name' => 'reaquila',
        'ip' => '203.0.113.10',
        'agent_port' => 9100,
        'token' => FakeAgentGateway::TOKEN,
        'tls_fingerprint' => FakeAgentGateway::FINGERPRINT,
        ...$attributes,
    ]))->save();
}

/** One poller tick, waiting for everything it started (monitoreo env.tick). */
function pollOnce(Poller $poller): void
{
    $poller->tick();
    $poller->drain();
}

it('goes online, polls every section, and records identity and metrics (RF-13.02)', function () {
    pollOnce($this->poller);

    $server = $this->server->refresh();
    expect($server->status)->toBe('online')
        ->and($server->last_seen_at)->not->toBeNull()
        ->and($server->agent_version)->toBe('v-test')
        ->and($server->tls_spki_pin)->toBe(FakeAgentGateway::pinFor(FakeAgentGateway::FINGERPRINT))
        ->and($this->agent->hits('metrics'))->toBe(0); // sections wait until the agent is online
    Event::assertDispatched(ServerStatusChanged::class, fn ($event) => $event->status === 'online'
        && $event->broadcastOn()->name === "private-monitoring.{$this->admin->company_id}");

    pollOnce($this->poller);

    $snapshots = ServerSnapshot::query()->where('server_id', $server->id)->get()->keyBy('section');
    expect(json_decode($snapshots['metrics']->data, true)['disks'][0]['used_percent'])->toBe(65)
        ->and($snapshots['system']->data)->not->toBeNull()
        ->and($snapshots['pm2']->data)->toBeNull()
        ->and($snapshots['pm2']->error)->toBe('el agente es anterior a esta función: hay que actualizarlo');
    $server->refresh();
    expect($server->ssh_port)->toBe(1080)->and($server->hostname)->toBe('mail.reaquila.com')->and($server->os)->toBe('Ubuntu 24.10');
    $points = DB::table('monitoring_metrics_raw')->get();
    expect($points)->toHaveCount(1)->and((float) $points[0]->rx_bps)->toBe(1000.0)->and($points[0]->company_id)->toBe($this->admin->company_id);
    Event::assertDispatched(SnapshotSaved::class, fn ($event) => $event->section === 'metrics' && $event->broadcastWith()['server_id'] === $server->id);
    Event::assertDispatched(MetricsCollected::class, fn ($event) => $event->broadcastWith()['metrics']['disk_percent'] === 65.0
        && ! array_key_exists('full', $event->broadcastWith()));

    // Nothing is due one second later.
    $metricsHits = $this->agent->hits('metrics');
    $this->travel(1)->seconds();
    pollOnce($this->poller);
    expect($this->agent->hits('metrics'))->toBe($metricsHits);
    expect(app(PollerSignals::class)->pollerAlive(now()->getTimestamp()))->toBeTrue();
});

it('tells a dead agent from a dead server through the SSH port (CA-13.03)', function () {
    pollOnce($this->poller);
    pollOnce($this->poller);
    $status = fn () => $this->server->refresh()->status;

    $this->agent->broken = true;
    $this->agent->sshUp = true;
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($status())->toBe('online'); // one failure is tolerated

    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($status())->toBe('agent_down');

    $this->agent->sshUp = false;
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($status())->toBe('down');

    $this->agent->broken = false;
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($status())->toBe('online');

    $seen = [];
    Event::assertDispatched(ServerStatusChanged::class, function ($event) use (&$seen) {
        $seen[] = $event->status;

        return true;
    });
    expect($seen)->toBe(['online', 'agent_down', 'down', 'online']);
});

it('ends as agent_down when the agent rejects the token', function () {
    $this->server->token = 'not-the-token-at-all-0123';
    $this->server->save();
    $this->agent->sshUp = true;

    pollOnce($this->poller);
    $this->travel(31)->seconds();
    pollOnce($this->poller);

    expect($this->server->refresh()->status)->toBe('agent_down');
});

it('never sends the token to an agent whose certificate does not match (CA-13.02)', function () {
    $this->server->tls_fingerprint = str_repeat('0', 64);
    $this->server->save();

    pollOnce($this->poller);
    $this->travel(31)->seconds();
    pollOnce($this->poller);

    expect($this->server->refresh()->status)->toBe('down')
        ->and($this->server->tls_spki_pin)->toBeNull()
        ->and($this->agent->pinCalls)->toBe(2)
        ->and($this->agent->tokensSeen)->toBe([]);
});

it('keeps a typed SSH port over the one the agent reports', function () {
    $this->server->ssh_port = 2222;
    $this->server->save();

    pollOnce($this->poller);
    pollOnce($this->poller);

    expect($this->server->refresh()->ssh_port)->toBe(2222);
});

it('does not retry a section an old agent does not serve before its interval (CA-13.04)', function () {
    pollOnce($this->poller);
    pollOnce($this->poller);
    expect($this->agent->hits('large_files'))->toBe(1)
        ->and(ServerSnapshot::query()->where('section', 'large_files')->value('error'))->toContain('actualizarlo');

    for ($i = 0; $i < 10; $i++) {
        $this->travel(30)->minutes();
        pollOnce($this->poller);
    }
    expect($this->agent->hits('large_files'))->toBe(1);

    $this->travel(1)->hours();
    pollOnce($this->poller);
    expect($this->agent->hits('large_files'))->toBe(2);
});

it('retries a failing section with back-off and keeps its last good data (CA-13.04)', function () {
    $this->agent->sections['packages'] = ['manager' => 'apt', 'upgradable_list' => []];
    pollOnce($this->poller);
    pollOnce($this->poller);

    $this->agent->failing['packages'] = true;
    $this->poller->tick(); // nothing due yet
    app(PollerSignals::class)->requestRefresh($this->server->id, 'packages');
    pollOnce($this->poller);
    $snapshot = ServerSnapshot::query()->where('section', 'packages')->sole();
    expect($this->agent->hits('packages'))->toBe(2)
        ->and($snapshot->error)->toBe('agent 503: collector failed')
        ->and(json_decode($snapshot->data, true))->toBe(['manager' => 'apt', 'upgradable_list' => []]);

    $this->travel(119)->seconds();
    pollOnce($this->poller);
    expect($this->agent->hits('packages'))->toBe(2);
    $this->travel(1)->seconds();
    pollOnce($this->poller);
    expect($this->agent->hits('packages'))->toBe(3);
});

it('polls metrics every 5 s while someone watches, without flooding the raw table (CA-13.06)', function () {
    pollOnce($this->poller);
    pollOnce($this->poller);
    $hits = $this->agent->hits('metrics');

    app(PollerSignals::class)->beat($this->server->id);
    pollOnce($this->poller);
    expect($this->agent->hits('metrics'))->toBe($hits + 1); // first beat: right away
    Event::assertDispatched(MetricsCollected::class, fn ($event) => isset($event->broadcastWith()['full']->cpu));

    $this->travel(5)->seconds();
    pollOnce($this->poller);
    expect($this->agent->hits('metrics'))->toBe($hits + 2);

    // No beat for 30 s: the run already scheduled happens, then back to 1 min.
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    $afterLive = $this->agent->hits('metrics');
    $this->travel(6)->seconds();
    pollOnce($this->poller);
    expect($this->agent->hits('metrics'))->toBe($afterLive)
        ->and(DB::table('monitoring_metrics_raw')->count())->toBe(1);
});

it('raises and resolves alerts from health and sections as it polls (RF-13.04)', function () {
    $firing = fn (string $rule) => Alert::query()->where('rule', $rule)->where('state', 'firing')->get();
    pollOnce($this->poller);
    pollOnce($this->poller);

    // The disk alert needs two evaluations in a row.
    $this->agent->sections['metrics'] = FakeAgentGateway::metricsWithDisk(96);
    $this->travel(61)->seconds();
    pollOnce($this->poller);
    expect($firing('disk_usage'))->toHaveCount(0);
    $this->travel(61)->seconds();
    pollOnce($this->poller);
    expect($firing('disk_usage')->sole()->only(['target', 'severity', 'company_id']))
        ->toBe(['target' => '/', 'severity' => 'critical', 'company_id' => $this->admin->company_id]);

    // The agent breaks but SSH answers: agent_down from the second failure.
    $this->agent->broken = true;
    $this->agent->sshUp = true;
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($firing('agent_down'))->toHaveCount(0);
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($firing('agent_down'))->toHaveCount(1);

    // SSH gone too: server_down, and agent_down resolves.
    $this->agent->sshUp = false;
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    expect($firing('server_down')->sole()->message)->toBe('El servidor no responde (ni el agente ni SSH)')
        ->and($firing('agent_down'))->toHaveCount(0);

    // Back online with a sane disk: everything resolves.
    $this->agent->broken = false;
    $this->agent->sections['metrics'] = FakeAgentGateway::metricsWithDisk(50);
    $this->travel(31)->seconds();
    pollOnce($this->poller);
    pollOnce($this->poller);
    expect($this->server->refresh()->status)->toBe('online')
        ->and(Alert::query()->where('state', 'firing')->count())->toBe(0)
        ->and(Alert::query()->count())->toBe(3);
});

it('forgets the alert state of a deleted server', function () {
    pollOnce($this->poller);
    $state = app(AlertState::class);
    $state->saveStreaks($this->server->id, 'disk_usage', ['/' => ['since' => 1, 'count' => 1]]);

    $this->server->delete();
    app(PollerSignals::class)->requestReload();
    pollOnce($this->poller);

    expect($state->streaks($this->server->id, 'disk_usage'))->toBe([]);
});

it('picks up a new server at once when asked to reload, and skips disabled ones', function () {
    pollOnce($this->poller);
    $second = pollerServer($this->admin, ['name' => 'segundo']);
    $disabled = pollerServer($this->admin, ['name' => 'apagado', 'enabled' => false]);
    app(PollerSignals::class)->requestReload();

    pollOnce($this->poller);

    expect($second->refresh()->status)->toBe('online')->and($disabled->refresh()->status)->toBe('unknown');
});

it('runs as a command and leaves a tick for /health', function () {
    $this->app->forgetInstance(AgentGateway::class);
    Server::query()->delete();

    expect(Artisan::call('monitoring:poll', ['--max-time' => 1]))->toBe(0)
        ->and(app(PollerSignals::class)->pollerAlive(now()->getTimestamp()))->toBeTrue();
});

it('hands the saved scan and the new one to the notices, reading the saved one before replacing it (D-073)', function () {
    Event::fake([VulnerabilitiesScanned::class]);
    $scan = fn (string $at, array $findings) => ['scanned_at' => $at, 'items' => [['path' => '/var/www/a', 'manager' => 'npm', 'findings' => $findings]]];
    $this->agent->sections['vulnerabilities'] = $scan('2026-10-14T08:05:00Z', []);
    pollOnce($this->poller);
    pollOnce($this->poller);
    Event::assertNotDispatched(VulnerabilitiesScanned::class); // the first scan of a server is the baseline

    $this->agent->sections['vulnerabilities'] = $scan('2026-10-15T08:05:00Z', [['package' => 'axios', 'severity' => 'high', 'title' => 'SSRF']]);
    app(PollerSignals::class)->requestRefresh($this->server->id, 'vulnerabilities');
    pollOnce($this->poller);

    Event::assertDispatched(VulnerabilitiesScanned::class, fn (VulnerabilitiesScanned $event) => $event->serverId === $this->server->id
        && $event->serverName === 'reaquila' && $event->previous['scanned_at'] === '2026-10-14T08:05:00Z'
        && $event->next['items'][0]['findings'][0]['package'] === 'axios');
});
