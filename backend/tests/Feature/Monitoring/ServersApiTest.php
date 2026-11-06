<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeAgentGateway;

const SERVERS_URL = '/api/v1/monitoring/servers';

function monitoredServer(User $admin, array $attributes = []): Server
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

function developerOf(User $admin): User
{
    return User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create();
}

/** @return array<string, mixed> */
function serverInput(array $overrides = []): array
{
    return [
        'name' => 'reaquila',
        'ip' => '203.0.113.10',
        'token' => 'agent-token-0123456789abcdef',
        'tls_fingerprint' => strtoupper(implode(':', str_split(FakeAgentGateway::FINGERPRINT, 2))),
        ...$overrides,
    ];
}

it('creates a server and never returns its token (CA-13.01)', function () {
    $admin = provisionCompany();

    $response = $this->actingAs($admin)->postJson(SERVERS_URL, serverInput(['ssh_port' => 1080]))
        ->assertCreated()
        ->assertJsonPath('message', 'Servidor creado.')
        ->assertJsonPath('data.name', 'reaquila')
        ->assertJsonPath('data.agent_port', 9100)
        ->assertJsonPath('data.ssh_port', 1080)
        ->assertJsonPath('data.status', 'unknown')
        ->assertJsonPath('data.metrics', null)
        ->assertJsonPath('data.active_alerts', 0);

    expect($response->getContent())->not->toContain('agent-token')->not->toContain('"token"');
    $server = Server::query()->findOrFail($response->json('data.id'));
    $raw = DB::table('monitoring_servers')->where('id', $server->id)->value('token');
    expect($raw)->not->toContain('agent-token')
        ->and($server->token)->toBe('agent-token-0123456789abcdef')
        ->and($server->tls_fingerprint)->toBe(FakeAgentGateway::FINGERPRINT);

    $activity = ActivityLog::query()->where('event', 'server.created')->sole();
    expect($activity->after)->not->toHaveKey('token')->and($activity->after['name'])->toBe('reaquila');
    expect(app(PollerSignals::class)->take([])['reload'])->toBe(1);
});

it('validates like monitoreo, with its messages', function () {
    $admin = provisionCompany();
    $this->actingAs($admin);

    $this->postJson(SERVERS_URL, ['name' => '', 'ip' => 'nope', 'ssh_port' => 70000, 'token' => 'short', 'tls_fingerprint' => 'xyz'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'El nombre es obligatorio (máximo 100 caracteres).')
        ->assertJsonPath('errors.ip.0', 'Ingresá una IP válida.')
        ->assertJsonPath('errors.ssh_port.0', 'Puerto inválido.')
        ->assertJsonPath('errors.token.0', 'El token es demasiado corto.')
        ->assertJsonPath('errors.tls_fingerprint.0', 'El fingerprint debe ser el SHA-256 en hexadecimal (64 caracteres).');

    $this->postJson(SERVERS_URL, ['name' => 'x', 'ip' => '10.0.0.1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', 'El token del agente es obligatorio.')
        ->assertJsonPath('errors.tls_fingerprint.0', 'El fingerprint debe ser el SHA-256 en hexadecimal (64 caracteres).');

    $this->postJson(SERVERS_URL, serverInput(['ip' => '::ffff:10.0.0.1']))->assertCreated()->assertJsonPath('data.ip', '10.0.0.1');
    $this->postJson(SERVERS_URL, serverInput())->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ya existe un servidor con ese nombre.');
});

it('lists servers with their metrics summary and shows every section (RF-13.03)', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin, ['status' => 'online']);
    monitoredServer($admin, ['name' => 'apagado', 'enabled' => false]);
    $snapshot = fn (string $section, string $data) => ServerSnapshot::query()->insert([
        'server_id' => $server->id, 'section' => $section, 'company_id' => $admin->company_id,
        'collected_at' => '2026-11-06 12:00:00', 'fetched_at' => '2026-11-06 12:00:01', 'agent_version' => 'v-test', 'data' => $data,
    ]);
    $snapshot('metrics', json_encode(FakeAgentGateway::metricsWithDisk(65)));
    $snapshot('system', '{"hostname":"mail","extra":{},"ips":[]}');
    ServerSnapshot::query()->insert(['server_id' => $server->id, 'section' => 'pm2', 'company_id' => $admin->company_id,
        'fetched_at' => '2026-11-06 12:00:01', 'error' => 'el agente es anterior a esta función: hay que actualizarlo']);

    $this->actingAs(developerOf($admin))->getJson(SERVERS_URL)
        ->assertOk()
        ->assertJsonPath('data.0.name', 'apagado')
        ->assertJsonPath('data.0.status', 'disabled')
        ->assertJsonPath('data.1.status', 'online')
        ->assertJsonPath('data.1.metrics.cpu_percent', 10)
        ->assertJsonPath('data.1.metrics.disk_percent', 65)
        ->assertJsonPath('data.1.metrics.uptime_seconds', 1724400)
        ->assertJsonPath('data.1.metrics.collected_at', '2026-11-06T12:00:00Z')
        ->assertJsonMissingPath('meta');

    $response = $this->getJson(SERVERS_URL."/{$server->id}")
        ->assertOk()
        ->assertJsonPath('data.server.id', $server->id)
        ->assertJsonPath('data.server.metrics.memory_percent', 60)
        ->assertJsonPath('data.sections.metrics.data.network.0.rx_bytes_per_sec', 1000)
        ->assertJsonPath('data.sections.system.agent_version', 'v-test')
        ->assertJsonPath('data.sections.pm2.data', null)
        ->assertJsonPath('data.sections.pm2.error', 'el agente es anterior a esta función: hay que actualizarlo')
        ->assertJsonPath('data.sections.packages', null);

    expect(array_keys($response->json('data.sections')))->toHaveCount(15)
        ->and($response->getContent())->toContain('"extra":{}');
});

it('asks for the token again when the IP, port or fingerprint change (CA-13.07)', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin, ['status' => 'online']);
    $server->rememberPin('some-pin');
    $this->actingAs($admin);
    $put = fn (array $input) => $this->putJson(SERVERS_URL."/{$server->id}", ['name' => 'reaquila', 'ip' => '203.0.113.10', ...$input]);

    foreach ([['ip' => '203.0.113.11'], ['agent_port' => 9200], ['tls_fingerprint' => str_repeat('b', 64)]] as $change) {
        $put($change)->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'Cambiaste la IP, el puerto del agente o el fingerprint: volvé a cargar el token.');
    }

    // Same address: the stored token and fingerprint stay, and the server starts over as unknown.
    $put(['name' => 'reaquila-2'])->assertOk()->assertJsonPath('message', 'Servidor actualizado.')->assertJsonPath('data.status', 'unknown');
    $server->refresh();
    expect($server->token)->toBe(FakeAgentGateway::TOKEN)->and($server->tls_spki_pin)->toBe('some-pin');

    $put(['ip' => '203.0.113.11', 'token' => 'a-brand-new-token-0123456'])->assertOk()->assertJsonPath('data.ip', '203.0.113.11');
    $server->refresh();
    expect($server->token)->toBe('a-brand-new-token-0123456')
        ->and($server->tls_spki_pin)->toBeNull() // derived from the old address: recomputed on next contact
        ->and(ActivityLog::query()->where('event', 'server.updated')->count())->toBe(2);
});

it('deletes a server with its snapshots', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin);
    ServerSnapshot::query()->insert(['server_id' => $server->id, 'section' => 'metrics', 'company_id' => $admin->company_id, 'fetched_at' => now()]);

    $this->actingAs($admin)->deleteJson(SERVERS_URL."/{$server->id}")->assertOk()->assertJsonPath('message', 'Servidor eliminado.');

    expect(Server::query()->count())->toBe(0)
        ->and(ServerSnapshot::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('event', 'server.deleted')->exists())->toBeTrue();
});

it('lets monitoring.view read and monitoring.manage change (ADR-0002 §10)', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin);
    $url = SERVERS_URL."/{$server->id}";
    $this->actingAs(developerOf($admin));

    $this->getJson(SERVERS_URL)->assertOk();
    $this->getJson($url)->assertOk();
    $this->getJson("{$url}/metrics")->assertOk();
    $this->postJson("{$url}/refresh")->assertStatus(202);
    $this->postJson("{$url}/live")->assertOk();
    $this->postJson(SERVERS_URL, serverInput(['name' => 'otro']))->assertForbidden();
    $this->putJson($url, serverInput())->assertForbidden();
    $this->deleteJson($url)->assertForbidden();
    $this->postJson("{$url}/test")->assertForbidden();

    $collaborator = User::factory()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();
    $this->actingAs($collaborator)->getJson(SERVERS_URL)->assertForbidden();

    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))
        ->create(['client_id' => Client::factory()->create(['company_id' => $admin->company_id])->id]);
    $this->actingAs($portalUser)->getJson(SERVERS_URL)->assertForbidden();
});

it('hides servers of another company', function () {
    $admin = provisionCompany('a@nexo.com');
    $foreign = monitoredServer(provisionCompany('b@other.com'));

    $this->actingAs($admin)->getJson(SERVERS_URL."/{$foreign->id}")->assertNotFound();
    $this->getJson(SERVERS_URL)->assertOk()->assertJsonCount(0, 'data');
});

it('queues a forced refresh for the poller and validates the section', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin);
    $this->actingAs($admin);

    $this->postJson(SERVERS_URL."/{$server->id}/refresh", ['section' => 'pm2'])->assertStatus(202)->assertJsonPath('message', 'Actualización pedida.');
    $this->postJson(SERVERS_URL."/{$server->id}/refresh")->assertStatus(202);
    $this->postJson(SERVERS_URL."/{$server->id}/refresh", ['section' => 'nope'])
        ->assertUnprocessable()->assertJsonPath('errors.section.0', 'Sección desconocida.');

    expect(app(PollerSignals::class)->take([$server->id])['refresh'])->toBe([$server->id => ['pm2', null]]);
});

it('marks the server live while its detail beats (CA-13.06)', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin);

    $this->actingAs($admin)->postJson(SERVERS_URL."/{$server->id}/live")->assertOk();
    expect(app(PollerSignals::class)->take([$server->id])['live'])->toBe([$server->id]);

    $this->travel(PollerSignals::LIVE_TTL + 1)->seconds();
    expect(app(PollerSignals::class)->take([$server->id])['live'])->toBe([]);
});

it('serves metric series: raw up to 7 days, hourly beyond', function () {
    $admin = provisionCompany();
    $server = monitoredServer($admin);
    $this->freezeTime();
    $point = ['company_id' => $admin->company_id, 'server_id' => $server->id, 'cpu' => 10, 'memory' => 60, 'swap' => 20, 'disk' => 65, 'load1' => 0.3, 'rx_bps' => 1000, 'tx_bps' => 500];
    DB::table('monitoring_metrics_raw')->insert([
        ['ts' => now()->subMinutes(10)->startOfMinute(), ...$point],
        ['ts' => now()->subDays(2), ...$point],
    ]);
    DB::table('monitoring_metrics_hourly')->insert([
        'hour' => now()->subDays(10)->startOfHour(), 'cpu_max' => 95, 'memory_max' => 70, 'swap_max' => 30, 'disk_max' => 66,
        'load1_max' => 1.5, 'samples' => 60, ...$point,
    ]);
    $url = SERVERS_URL."/{$server->id}/metrics";
    $this->actingAs($admin);

    $this->getJson("{$url}?range=1h")->assertOk()
        ->assertJsonPath('data.range', '1h')
        ->assertJsonPath('data.resolution', 'raw')
        ->assertJsonCount(1, 'data.points')
        ->assertJsonPath('data.points.0.ts', now()->subMinutes(10)->startOfMinute()->toIso8601ZuluString())
        ->assertJsonPath('data.points.0.rx_bps', 1000)
        ->assertJsonMissingPath('data.points.0.cpu_max');
    $this->getJson($url)->assertOk()->assertJsonPath('data.range', '24h')->assertJsonCount(1, 'data.points');
    $this->getJson("{$url}?range=7d")->assertOk()->assertJsonCount(2, 'data.points');
    $this->getJson("{$url}?range=30d")->assertOk()
        ->assertJsonPath('data.resolution', 'hourly')
        ->assertJsonCount(1, 'data.points')
        ->assertJsonPath('data.points.0.cpu_max', 95);
    $this->getJson("{$url}?range=2w")->assertUnprocessable()->assertJsonPath('errors.range.0', 'Usá 1h, 24h, 7d, 30d o 1y.');
});

it('tests the connection and stores the pin', function () {
    $admin = provisionCompany();
    $this->app->instance(AgentGateway::class, $agent = new FakeAgentGateway);
    $agent->sections['health'] = ['status' => 'ok', 'version' => 'v-test', 'hostname' => 'mail.reaquila.com'];
    $server = monitoredServer($admin);

    $this->actingAs($admin)->postJson(SERVERS_URL."/{$server->id}/test")
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.agent_version', 'v-test')
        ->assertJsonPath('data.hostname', 'mail.reaquila.com');

    expect($server->refresh()->tls_spki_pin)->toBe(FakeAgentGateway::pinFor(FakeAgentGateway::FINGERPRINT));
});

it('refuses an agent whose certificate does not match before sending the token (CA-13.02)', function () {
    $admin = provisionCompany();
    $this->app->instance(AgentGateway::class, $agent = new FakeAgentGateway);
    $server = monitoredServer($admin, ['tls_fingerprint' => str_repeat('0', 64)]);

    $this->actingAs($admin)->postJson(SERVERS_URL."/{$server->id}/test")
        ->assertOk()
        ->assertExactJson(['data' => ['ok' => false, 'error' => 'la huella no coincide'], 'message' => null, 'errors' => null]);

    expect($agent->tokensSeen)->toBe([])->and($server->refresh()->tls_spki_pin)->toBeNull();
});
