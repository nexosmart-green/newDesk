<?php

use App\Domain\Identity\DefaultRoles;

it('lists users of the own company only, paginated', function () {
    $admin = adminUser('a@nexo.com');
    userWithRole($admin);
    userWithRole(adminUser('b@other.com'));

    $this->actingAs($admin)->getJson('/api/v1/users')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data', 'meta' => ['total'], 'message', 'errors']);
});

it('changes a user role and the new permissions apply on the next request', function () {
    $admin = adminUser();
    $collaborator = userWithRole($admin);
    $manager = roleNamed($admin, DefaultRoles::MANAGER);

    $this->actingAs($admin)->putJson("/api/v1/users/{$collaborator->id}/role", ['role_id' => $manager->id])
        ->assertOk()->assertJsonPath('data.role.name', DefaultRoles::MANAGER);

    $this->actingAs($collaborator->fresh())->getJson('/api/v1/me')->assertJsonFragment(['hours.allocate']);
});

it('never leaves the company without an Admin', function () {
    $admin = adminUser();

    $this->actingAs($admin)->putJson("/api/v1/users/{$admin->id}/role", ['role_id' => roleNamed($admin, DefaultRoles::COLLABORATOR)->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'last_admin');
});

it('returns 404 for users of another company and rejects their roles', function () {
    $admin = adminUser('a@nexo.com');
    $other = adminUser('b@other.com');
    $mine = userWithRole($admin);

    $this->actingAs($admin)->putJson("/api/v1/users/{$other->id}/role", ['role_id' => roleNamed($admin, DefaultRoles::MANAGER)->id])
        ->assertNotFound();
    $this->actingAs($admin)->putJson("/api/v1/users/{$mine->id}/role", ['role_id' => roleNamed($other, DefaultRoles::MANAGER)->id])
        ->assertUnprocessable()->assertJsonValidationErrors('role_id');
});
