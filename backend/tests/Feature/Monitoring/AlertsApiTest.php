<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Models\SiteCheck;

/**
 * monitoreo GET /api/alerts, /api/servers/{id}/sites and /api/inventory/projects under
 * /api/v1/monitoring (ADR-0002 §10), and `active_alerts` in the server summaries.
 */
beforeEach(function () {
    $this->freezeTime();
    $this->admin = provisionCompany();
    $this->server = alertsApiServer($this->admin, 'reaquila');
    $this->developer = User::factory()->forRole(roleNamed($this->admin, DefaultRoles::DEVELOPER))->create();
});

function alertsApiServer(User $admin, string $name): Server
{
    return tap((new Server)->forceFill([
        'company_id' => $admin->company_id, 'name' => $name, 'ip' => '203.0.113.10', 'agent_port' => 9100,
        'token' => 'agent-token-0123456789', 'tls_fingerprint' => str_repeat('a', 64), 'status' => 'online',
    ]))->save();
}

function storedAlert(Server $server, array $attributes = []): Alert
{
    return Alert::query()->create([
        'company_id' => $server->company_id, 'server_id' => $server->id, 'rule' => 'disk_usage', 'target' => '/',
        'severity' => 'warning', 'state' => 'firing', 'message' => 'Disco / al 88.0 %', 'value' => '88.0',
        'first_seen_at' => now()->subHour(), 'last_seen_at' => now(), ...$attributes,
    ]);
}

it('lists alerts as monitoreo does, the latest seen first, filtered by state and server (RF-13.04)', function () {
    $other = alertsApiServer($this->admin, 'clientes');
    $resolved = storedAlert($this->server, ['state' => 'resolved', 'resolved_at' => now()->subMinutes(10), 'last_seen_at' => now()->subMinutes(10)]);
    $firing = storedAlert($this->server, ['rule' => 'cpu_high', 'target' => '', 'severity' => 'critical']);
    storedAlert($other, ['last_seen_at' => now()->subMinute()]);

    $this->actingAs($this->developer)->getJson('/api/v1/monitoring/alerts')
        ->assertOk()
        ->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 3])
        ->assertJsonPath('data.0', [
            'id' => $firing->id, 'server_id' => $this->server->id, 'server_name' => 'reaquila', 'rule' => 'cpu_high', 'target' => '',
            'severity' => 'critical', 'state' => 'firing', 'message' => 'Disco / al 88.0 %', 'value' => '88.0',
            'first_seen_at' => now()->subHour()->toIso8601ZuluString(), 'last_seen_at' => now()->toIso8601ZuluString(),
            'resolved_at' => null, 'last_notified_at' => null,
        ])
        ->assertJsonPath('data.1.server_name', 'clientes')
        ->assertJsonPath('data.2.id', $resolved->id)
        ->assertJsonPath('data.2.resolved_at', now()->subMinutes(10)->toIso8601ZuluString())
        ->assertJsonPath('message', null)
        ->assertJsonPath('errors', null);

    $this->getJson('/api/v1/monitoring/alerts?state=resolved')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $resolved->id);
    $this->getJson('/api/v1/monitoring/alerts?state=firing')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson("/api/v1/monitoring/alerts?server_id={$other->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.server_name', 'clientes');
});

it('pages 10 at a time, all together under 20, and answers the last page past the end (D-032, CA-13.25)', function () {
    foreach (range(1, 19) as $minute) {
        storedAlert($this->server, ['target' => "/d{$minute}", 'last_seen_at' => now()->subMinutes($minute)]);
    }
    $this->actingAs($this->developer)->getJson('/api/v1/monitoring/alerts')
        ->assertJsonCount(19, 'data')->assertJsonPath('meta', ['page' => 1, 'per_page' => 20, 'total' => 19]);

    foreach (range(20, 25) as $minute) {
        storedAlert($this->server, ['target' => "/d{$minute}", 'last_seen_at' => now()->subMinutes($minute)]);
    }
    $this->getJson('/api/v1/monitoring/alerts?page=2')
        ->assertJsonCount(10, 'data')->assertJsonPath('meta', ['page' => 2, 'per_page' => 10, 'total' => 25])
        ->assertJsonPath('data.0.target', '/d11');
    $this->getJson('/api/v1/monitoring/alerts?page=9')
        ->assertJsonCount(5, 'data')->assertJsonPath('meta.page', 3)->assertJsonPath('data.4.target', '/d25');
});

it('validates the filters with monitoreo\'s messages', function () {
    $this->actingAs($this->developer);

    $this->getJson('/api/v1/monitoring/alerts?state=open')->assertUnprocessable()->assertJsonPath('errors.state.0', 'Usá firing o resolved.');
    $this->getJson('/api/v1/monitoring/alerts?page=0')->assertUnprocessable()->assertJsonPath('errors.page.0', 'Página inválida.');
    $this->getJson('/api/v1/monitoring/alerts?page=abc')->assertUnprocessable()->assertJsonPath('errors.page.0', 'Página inválida.');
    $this->getJson('/api/v1/monitoring/alerts?server_id=x')->assertUnprocessable()->assertJsonPath('errors.server_id.0', 'Servidor inválido.');
});

it('counts only the firing alerts as active in the server list and detail', function () {
    storedAlert($this->server);
    storedAlert($this->server, ['target' => '/data']);
    storedAlert($this->server, ['state' => 'resolved', 'resolved_at' => now()]);

    $this->actingAs($this->developer)->getJson('/api/v1/monitoring/servers')->assertJsonPath('data.0.active_alerts', 2);
    $this->getJson("/api/v1/monitoring/servers/{$this->server->id}")->assertJsonPath('data.server.active_alerts', 2);
});

it('lists the site checks of a server with the certificate days left (RF-13.05)', function () {
    SiteCheck::query()->insert([
        ['server_id' => $this->server->id, 'server_name' => 'www.reaquila.com', 'company_id' => $this->admin->company_id, 'url' => 'https://www.reaquila.com/',
            'status_code' => 200, 'latency_ms' => 85, 'ok' => true, 'error' => null, 'ssl_expires_at' => now()->addDays(20)->addHour(), 'checked_at' => now()],
        ['server_id' => $this->server->id, 'server_name' => 'api.reaquila.com', 'company_id' => $this->admin->company_id, 'url' => 'https://api.reaquila.com/',
            'status_code' => null, 'latency_ms' => null, 'ok' => false, 'error' => 'timeout al conectar', 'ssl_expires_at' => null, 'checked_at' => now()],
    ]);

    $this->actingAs($this->developer)->getJson("/api/v1/monitoring/servers/{$this->server->id}/sites")
        ->assertOk()
        ->assertJsonPath('data.0', [
            'url' => 'https://api.reaquila.com/', 'server_name' => 'api.reaquila.com', 'status_code' => null, 'latency_ms' => null,
            'ok' => false, 'error' => 'timeout al conectar', 'ssl_expires_at' => null, 'ssl_days_left' => null, 'checked_at' => now()->toIso8601ZuluString(),
        ])
        ->assertJsonPath('data.1.ssl_days_left', 20)
        ->assertJsonPath('data.1.ssl_expires_at', now()->addDays(20)->addHour()->toIso8601ZuluString())
        ->assertJsonPath('data.1.latency_ms', 85);
});

it('indexes the project folders of every server for the search (RF-13.04)', function () {
    foreach (['projects', 'sites'] as $section) {
        ServerSnapshot::query()->insert(['server_id' => $this->server->id, 'section' => $section, 'company_id' => $this->admin->company_id,
            'collected_at' => now(), 'fetched_at' => now(), 'data' => file_get_contents(base_path("tests/Fixtures/monitoreo/servers/clientes/{$section}.json"))]);
    }

    $response = $this->actingAs($this->developer)->getJson('/api/v1/monitoring/inventory/projects')->assertOk();

    $playinn = collect($response->json('data'))->firstWhere('name', 'playinn');
    expect($playinn['server_name'])->toBe('reaquila')
        ->and($playinn['server_id'])->toBe($this->server->id)
        ->and($playinn['domains'])->toBe(['clientes.nexosmart.com.ar'])
        // Server tools stay in the inventory.
        ->and($response->json('data.*.path'))->toContain('/var/www/nothinghere!', '/var/www/error_templates');
});

it('needs monitoring.view and hides other companies (ADR-0002 §10)', function () {
    $foreign = alertsApiServer(provisionCompany('b@other.com'), 'ajeno');
    storedAlert($foreign);
    $urls = ['/api/v1/monitoring/alerts', "/api/v1/monitoring/servers/{$this->server->id}/sites", '/api/v1/monitoring/inventory/projects'];

    $this->actingAs($this->developer)->getJson('/api/v1/monitoring/alerts')->assertJsonPath('meta.total', 0);
    $this->getJson("/api/v1/monitoring/servers/{$foreign->id}/sites")->assertNotFound();

    $collaborator = User::factory()->forRole(roleNamed($this->admin, DefaultRoles::COLLABORATOR))->create();
    $portalUser = User::factory()->forRole(roleNamed($this->admin, DefaultRoles::CLIENT))
        ->create(['client_id' => Client::factory()->create(['company_id' => $this->admin->company_id])->id]);
    foreach ([$collaborator, $portalUser] as $user) {
        foreach ($urls as $url) {
            $this->actingAs($user)->getJson($url)->assertForbidden();
        }
    }
});
