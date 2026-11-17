<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;

beforeEach(function () {
    // The test env broadcasts to "null", which authorizes nothing: use Reverb's (Pusher) signer here.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    require base_path('routes/channels.php');
});

function authorizeMonitoring(int $companyId): array
{
    return ['socket_id' => '1234.5678', 'channel_name' => "private-monitoring.{$companyId}"];
}

it('lets the team with monitoring.view subscribe to its company channel at /api/v1/broadcasting/auth', function () {
    $admin = adminUser();
    $developer = User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))->create();

    $this->actingAs($developer)->postJson('/api/v1/broadcasting/auth', authorizeMonitoring($admin->company_id))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('refuses other companies, users without monitoring.view, client users and guests', function () {
    $admin = adminUser('a@nexo.com');
    $other = adminUser('b@other.com');
    $collaborator = User::factory()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();
    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))
        ->create(['client_id' => Client::factory()->create(['company_id' => $admin->company_id])->id]);

    $this->postJson('/api/v1/broadcasting/auth', authorizeMonitoring($admin->company_id))->assertUnauthorized();
    $this->actingAs($admin)->postJson('/api/v1/broadcasting/auth', authorizeMonitoring($other->company_id))->assertForbidden();
    $this->actingAs($collaborator)->postJson('/api/v1/broadcasting/auth', authorizeMonitoring($admin->company_id))->assertForbidden();
    $this->actingAs($portalUser)->postJson('/api/v1/broadcasting/auth', authorizeMonitoring($admin->company_id))->assertForbidden();
});

it('lets client users subscribe only to their own client portal channel (RF-13.07)', function () {
    $admin = adminUser();
    [$playinn, $other] = Client::factory()->count(2)->create(['company_id' => $admin->company_id]);
    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $playinn->id]);
    $channel = fn (int $clientId) => ['socket_id' => '1234.5678', 'channel_name' => "private-portal.client.{$clientId}"];

    $this->actingAs($portalUser)->postJson('/api/v1/broadcasting/auth', $channel($playinn->id))
        ->assertOk()
        ->assertJsonStructure(['auth']);
    $this->actingAs($portalUser)->postJson('/api/v1/broadcasting/auth', $channel($other->id))->assertForbidden();
    $this->actingAs($admin)->postJson('/api/v1/broadcasting/auth', $channel($playinn->id))->assertForbidden();
});
