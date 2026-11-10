<?php

use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Events\SnapshotSaved;
use App\Domain\Monitoring\Jobs\CheckServerSites;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Models\SiteCheck;
use App\Domain\Monitoring\Polling\Poller;
use App\Domain\Monitoring\Sites\SiteChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Support\FakeAgentGateway;

/**
 * RF-13.05, monitoreo Poller.checkSites and fairness_test TestCheckSitesCapsNames: one round of a
 * server's site checks, as the queued job runs it, with fake DNS and HTTP.
 */
beforeEach(function () {
    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    Event::fake([SnapshotSaved::class, AlertChanged::class]);
    $this->app->instance(SiteChecker::class, new SiteChecker(fn (string $name) => ['104.16.0.1']));
    $admin = adminUser();
    $this->server = tap((new Server)->forceFill([
        'company_id' => $admin->company_id, 'name' => 'reaquila', 'ip' => '203.0.113.10', 'agent_port' => 9100,
        'token' => FakeAgentGateway::TOKEN, 'tls_fingerprint' => FakeAgentGateway::FINGERPRINT,
    ]))->save();
    $this->sites = function (array $names) {
        ServerSnapshot::query()->upsert([[
            'server_id' => $this->server->id, 'section' => 'sites', 'company_id' => $this->server->company_id,
            'collected_at' => now(), 'fetched_at' => now(), 'error' => null,
            'data' => json_encode(['items' => array_map(fn (string $name) => ['server_names' => [$name]], $names)]),
        ]], ['server_id', 'section'], ['data']);
    };
    $this->round = fn () => CheckServerSites::dispatchSync($this->server->id);
});

it('saves each check and its history, broadcasts them and opens site_down on the second down round (CA-13.09)', function () {
    ($this->sites)(['www.reaquila.com', 'api.reaquila.com']);
    Http::fake(['https://www.reaquila.com/' => Http::response('', 200), 'https://api.reaquila.com/' => Http::response('', 503)]);

    ($this->round)();
    $checks = SiteCheck::query()->orderBy('server_name')->get();
    expect($checks->pluck('server_name')->all())->toBe(['api.reaquila.com', 'www.reaquila.com'])
        ->and($checks[0]->only(['status_code', 'ok', 'url', 'company_id']))
        ->toBe(['status_code' => 503, 'ok' => false, 'url' => 'https://api.reaquila.com/', 'company_id' => $this->server->company_id])
        ->and(DB::table('monitoring_site_check_history')->count())->toBe(2)
        ->and(Alert::query()->count())->toBe(0); // site_down needs two rounds
    Event::assertDispatched(SnapshotSaved::class, fn (SnapshotSaved $event) => $event->broadcastWith() === [
        'server_id' => $this->server->id, 'section' => 'site_checks', 'collected_at' => now()->toIso8601ZuluString(),
    ]);

    $this->travel(5)->minutes();
    ($this->round)();
    $alert = Alert::query()->sole();
    expect($alert->only(['rule', 'target', 'severity', 'message']))->toBe([
        'rule' => AlertRules::SITE_DOWN, 'target' => 'api.reaquila.com', 'severity' => 'critical',
        'message' => 'El sitio api.reaquila.com responde HTTP 503',
    ])->and(DB::table('monitoring_site_check_history')->count())->toBe(4);
});

it('forgets the names a server stopped serving and resolves their alerts', function () {
    ($this->sites)(['old.reaquila.com', 'www.reaquila.com']);
    Http::fake(['https://old.reaquila.com/' => Http::response('', 500), '*' => Http::response('', 200)]);
    ($this->round)();
    ($this->round)();
    expect(Alert::query()->where('state', 'firing')->count())->toBe(1);

    ($this->sites)(['www.reaquila.com']);
    ($this->round)();

    expect(SiteCheck::query()->pluck('server_name')->all())->toBe(['www.reaquila.com'])
        ->and(Alert::query()->sole()->state)->toBe('resolved');
});

it('keeps the last known certificate expiry when a check brings none', function () {
    ($this->sites)(['www.reaquila.com']);
    $expires = now()->addDays(2)->addHour()->toDateTimeString();
    SiteCheck::query()->insert(['server_id' => $this->server->id, 'server_name' => 'www.reaquila.com', 'company_id' => $this->server->company_id,
        'url' => 'https://www.reaquila.com/', 'status_code' => 200, 'ok' => true, 'ssl_expires_at' => $expires, 'checked_at' => now()->subMinutes(5)]);
    Http::fake(['*' => fn (Request $request) => Http::failedConnection()($request)]);

    ($this->round)();

    $check = SiteCheck::query()->sole();
    expect($check->ok)->toBeFalse()
        ->and($check->error)->not->toBeNull()
        ->and($check->ssl_expires_at->toDateTimeString())->toBe($expires);
    // Under 3 days left: critical at once (ssl_expiring needs one evaluation).
    expect(Alert::query()->where('rule', AlertRules::SSL_EXPIRING)->sole()->only(['severity', 'value']))->toBe(['severity' => 'critical', 'value' => '2']);
});

it('checks the first 50 names of a server', function () {
    ($this->sites)(array_map(fn (int $i) => sprintf('site%02d.example.com', $i), range(0, 59)));
    Http::fake();

    ($this->round)();

    Http::assertSentCount(50);
    expect(SiteCheck::query()->count())->toBe(50)
        ->and(SiteCheck::query()->orderByDesc('server_name')->value('server_name'))->toBe('site49.example.com');
});

it('does nothing until the agent reported its sites', function () {
    Http::fake();
    ($this->round)();

    Http::assertNothingSent();
    expect(SiteCheck::query()->count())->toBe(0);
});

it('is queued by the poller every 5 minutes, whatever the server status, one round at a time', function () {
    Queue::fake();
    $agent = new FakeAgentGateway; // broken health: the server never goes online
    $agent->broken = true;
    $this->app->instance(AgentGateway::class, $agent);
    $poller = app(Poller::class);

    $poller->tick();
    $poller->drain();
    Queue::assertPushedOn('monitoring', CheckServerSites::class, fn (CheckServerSites $job) => $job->serverId === $this->server->id);

    // Five minutes later the first round is still queued: no second one.
    $this->travel(5)->minutes();
    $poller->tick();
    $poller->drain();
    Queue::assertPushed(CheckServerSites::class, 1);
    expect($this->server->refresh()->status)->not->toBe('online');
});
