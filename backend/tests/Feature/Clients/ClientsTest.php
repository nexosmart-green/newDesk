<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;

it('creates, lists and updates clients (RF-02.01)', function () {
    $admin = provisionCompany();

    $id = $this->actingAs($admin)->postJson('/api/v1/clients', ['name' => 'Playinn', 'tax_id' => '30-12345678-9'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Playinn')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('errors', null)
        ->json('data.id');

    $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('data.0.name', 'Playinn')->assertJsonPath('meta.total', 1);

    $this->putJson("/api/v1/clients/{$id}", ['name' => 'Playinn SA', 'active' => false])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('refuses a duplicated client name in the same company', function () {
    $admin = provisionCompany();
    Client::factory()->create(['company_id' => $admin->company_id, 'name' => 'Playinn']);

    $this->actingAs($admin)->postJson('/api/v1/clients', ['name' => 'Playinn'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Ya hay un cliente con ese nombre.');
});

it('does not delete a client with projects: it must be inactivated (CA-02.01)', function () {
    $admin = provisionCompany();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    Project::factory()->create(['client_id' => $client->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/clients/{$client->id}")
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'client_has_projects');

    expect(Client::query()->find($client->id))->not->toBeNull();
});

it('deletes a client without projects together with its portal users', function () {
    $admin = provisionCompany();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $client->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/clients/{$client->id}")->assertOk();

    expect(User::query()->find($portalUser->id))->toBeNull();
});

it('only lets clients.manage create clients', function () {
    $admin = provisionCompany();
    $collaborator = User::factory()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();

    $this->actingAs($collaborator)->postJson('/api/v1/clients', ['name' => 'Playinn'])->assertForbidden();
});

it('hides clients from another company', function () {
    $admin = provisionCompany('a@nexo.com');
    $foreign = Client::factory()->create(['company_id' => provisionCompany('b@other.com')->company_id]);

    $this->actingAs($admin)->getJson("/api/v1/clients/{$foreign->id}")->assertNotFound();
});

it('keeps client users out of every team route (CA-13.14)', function () {
    $admin = provisionCompany();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $portalUser = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $client->id]);

    $this->actingAs($portalUser)->getJson('/api/v1/clients')->assertForbidden();
    $this->actingAs($portalUser)->getJson('/api/v1/projects')->assertForbidden();
    $this->actingAs($portalUser->fresh())->getJson('/api/v1/me')->assertOk();
});
