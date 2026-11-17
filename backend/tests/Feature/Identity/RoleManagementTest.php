<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\Role;
use App\Domain\Shared\Activity\ActivityLog;

it('lists roles with their permissions and the permission catalog', function () {
    $admin = adminUser();

    $this->actingAs($admin)->getJson('/api/v1/roles')
        ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('errors', null); // Admin, Gestor, Colaborador, Desarrollador, Cliente
    $this->actingAs($admin)->getJson('/api/v1/permissions')
        ->assertOk()->assertJsonFragment(['value' => 'hours.allocate', 'label' => 'Cargar / ampliar horas']);
});

it('creates and edits a role, auditing before/after (RNF-07)', function () {
    $admin = adminUser();

    $roleId = $this->actingAs($admin)->postJson('/api/v1/roles', ['name' => 'Líder', 'permissions' => ['tasks.assign', 'time.track']])
        ->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/roles/{$roleId}", ['name' => 'Líder', 'permissions' => ['tasks.assign', 'hours.allocate']])
        ->assertOk()->assertJsonPath('data.permissions', ['hours.allocate', 'tasks.assign']);

    $audit = ActivityLog::query()->where('event', 'role.updated')->firstOrFail();
    expect($audit->before['permissions'])->toBe(['tasks.assign', 'time.track'])
        ->and($audit->after['permissions'])->toBe(['hours.allocate', 'tasks.assign']);
});

it('validates names and permissions', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/roles', ['name' => DefaultRoles::MANAGER, 'permissions' => ['nope']])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'permissions.0']);
});

it('protects the system Admin role', function () {
    $admin = adminUser();
    $adminRole = roleNamed($admin, DefaultRoles::ADMIN);

    $this->actingAs($admin)->putJson("/api/v1/roles/{$adminRole->id}", ['name' => 'Admin', 'permissions' => []])
        ->assertUnprocessable()->assertJsonPath('data.code', 'system_role_locked');
    $this->actingAs($admin)->deleteJson("/api/v1/roles/{$adminRole->id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'system_role_locked');
});

it('does not delete a role in use', function () {
    $admin = adminUser();
    $collaborator = userWithRole($admin);

    $this->actingAs($admin)->deleteJson("/api/v1/roles/{$collaborator->role_id}")
        ->assertUnprocessable()->assertJsonPath('data.code', 'role_in_use');
});

it('returns 403 to users without roles.manage and 404 for roles of another company', function () {
    $admin = adminUser('a@nexo.com');
    $otherCompanyRole = roleNamed(adminUser('b@other.com'), DefaultRoles::MANAGER);
    $collaborator = userWithRole($admin);

    $this->actingAs($collaborator)->getJson('/api/v1/roles')->assertForbidden();
    $this->actingAs($collaborator)->postJson('/api/v1/roles', ['name' => 'X', 'permissions' => []])->assertForbidden();

    $this->actingAs($admin)->putJson("/api/v1/roles/{$otherCompanyRole->id}", ['name' => 'Hack', 'permissions' => []])->assertNotFound();
    expect(Role::query()->withoutGlobalScopes()->find($otherCompanyRole->id)->name)->toBe(DefaultRoles::MANAGER);
});
