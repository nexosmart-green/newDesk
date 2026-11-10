<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;

function clientFor(User $admin, array $attributes = []): Client
{
    return Client::factory()->create(['company_id' => $admin->company_id, ...$attributes]);
}

it('lets clients.manage create and edit clients (RF-02.01)', function () {
    $admin = adminUser();

    $id = $this->actingAs($admin)->postJson('/api/v1/clients', [
        'legal_name' => 'BH News S.A.', 'trade_name' => 'bhnews', 'tax_id' => '30-71234567-8', 'email' => 'hola@bhnews.com',
    ])->assertCreated()->assertJsonPath('data.display_name', 'bhnews')->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/clients/{$id}", ['legal_name' => 'BH News SRL', 'notes' => 'Cliente desde 2021'])
        ->assertOk()->assertJsonPath('data.legal_name', 'BH News SRL');
});

it('never deletes a client with projects: it gets inactivated (CA-02.01)', function () {
    $admin = adminUser();
    $client = clientFor($admin);
    $this->actingAs($admin)->postJson('/api/v1/projects', ['name' => 'Web', 'code_prefix' => 'web', 'type' => 'bag', 'client_id' => $client->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/clients/{$client->id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'client_has_projects');
    $this->actingAs($admin)->putJson("/api/v1/clients/{$client->id}/status", ['status' => 'inactive'])->assertOk();

    $this->actingAs($admin)->postJson('/api/v1/projects', ['name' => 'Otra', 'code_prefix' => 'otra', 'type' => 'bag', 'client_id' => $client->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'client_inactive');
});

it('deletes a client without projects', function () {
    $admin = adminUser();
    $client = clientFor($admin);

    $this->actingAs($admin)->deleteJson("/api/v1/clients/{$client->id}")->assertOk();
    expect(Client::query()->find($client->id))->toBeNull();
});

it('lets a Gestor read clients but not edit them, and hides other companies', function () {
    $admin = adminUser('a@nexo.com');
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $foreign = clientFor(adminUser('b@other.com'));

    $this->actingAs($manager)->getJson('/api/v1/clients')->assertOk();
    $this->actingAs($manager)->postJson('/api/v1/clients', ['legal_name' => 'X'])->assertForbidden();
    $this->actingAs(userWithRole($admin))->getJson('/api/v1/clients')->assertForbidden();
    $this->actingAs($admin)->getJson("/api/v1/clients/{$foreign->id}")->assertNotFound();
});
