<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\Notification;

// ADR-0002 §12: a client's portal user (RF-13.07) is not part of the team anywhere.
it('keeps client portal users out of the team lists, pickers and actions', function () {
    Notification::fake();
    $admin = adminUser();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $portal = User::factory()->forRole(roleNamed($admin, DefaultRoles::CLIENT))->create(['client_id' => $client->id, 'email' => 'dueno@cliente.com']);
    $project = Project::factory()->create(['company_id' => $admin->company_id, 'client_id' => $client->id]);
    $this->actingAs($admin);

    expect($this->getJson('/api/v1/users')->assertOk()->getContent())->not->toContain('dueno@cliente.com')
        ->and($this->getJson('/api/v1/people')->assertOk()->getContent())->not->toContain('dueno@cliente.com');
    $this->getJson("/api/v1/people/{$portal->id}")->assertNotFound();
    $this->putJson("/api/v1/users/{$portal->id}/role", ['role_id' => roleNamed($admin, DefaultRoles::COLLABORATOR)->id])->assertNotFound();
    $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $portal->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'member_inactive');

    // The client role is only given from Monitoreo, and it is locked like a system role.
    $teammate = userWithRole($admin);
    $clientRole = roleNamed($admin, DefaultRoles::CLIENT);
    expect($clientRole->is_system)->toBeFalse()->and($portal->requiresTwoFactor())->toBeFalse();
    $this->putJson("/api/v1/users/{$teammate->id}/role", ['role_id' => $clientRole->id])->assertForbidden()->assertJsonPath('data.code', 'portal_role');
    $this->putJson("/api/v1/roles/{$clientRole->id}", ['name' => 'Otro', 'permissions' => []])->assertUnprocessable()->assertJsonPath('data.code', 'system_role_locked');
    $this->deleteJson("/api/v1/roles/{$clientRole->id}")->assertUnprocessable()->assertJsonPath('data.code', 'system_role_locked');

    // A portal user never gets a password: they enter with a mailed code.
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'dueno@cliente.com'])->assertOk();
    Notification::assertNothingSent();
});
