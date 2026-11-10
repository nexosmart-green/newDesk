<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

require_once __DIR__.'/../Mcp/McpTestHelpers.php';

function withoutModule(User $admin, string $roleName, Permission $module): void
{
    $role = roleNamed($admin, $roleName);
    $kept = array_values(array_filter(
        array_map(fn (string $value) => Permission::from($value), $role->permissionValues()),
        fn (Permission $permission) => $permission !== $module,
    ));
    test()->actingAs($admin)->putJson("/api/v1/roles/{$role->id}", [
        'name' => $role->name,
        'permissions' => array_map(fn (Permission $permission) => $permission->value, $kept),
    ])->assertOk();
}

it('lets the Admin turn a module off for a role, in the web and in MCP (pedido 2026-11-10)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $token = mcpToken($dev);

    startTimer($dev, $task)->assertOk();
    stopTimer($dev)->assertOk();

    withoutModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleTime);

    startTimer($dev->fresh(), $task)->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/timesheet?from=2026-11-01&to=2026-11-07')->assertForbidden();
    $result = mcpResult(mcpCall($token, 'start_timer', ['code' => $task->code]));
    expect($result['error'])->toBeTrue()->and($result['text'])->toContain('Horas y cronómetro');

    // Other modules and the always-on areas keep working.
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/tasks')->assertOk();
    $this->actingAs($dev->fresh())->getJson('/api/v1/me/profile')->assertOk();
    $this->actingAs($dev->fresh())->getJson('/api/v1/notifications')->assertOk();
});

it('exposes the modules in /me and in the permission catalog so the UI can show switches', function () {
    $admin = adminUser();

    $this->actingAs($admin)->getJson('/api/v1/permissions')
        ->assertOk()->assertJsonFragment(['value' => 'module.time', 'label' => 'Módulo Horas y cronómetro']);
    $this->actingAs(userWithRole($admin))->getJson('/api/v1/me')
        ->assertJsonFragment(['module.projects'])->assertJsonMissing(['module.reports']);
});

it('keeps every module for the Admin role, which cannot be edited', function () {
    $admin = adminUser();
    $adminRole = roleNamed($admin, DefaultRoles::ADMIN);

    expect($adminRole->permissionValues())->toContain(...array_map(fn (Permission $module) => $module->value, Permission::modules()));
    $this->actingAs($admin)->putJson("/api/v1/roles/{$adminRole->id}", ['name' => 'Admin', 'permissions' => []])
        ->assertUnprocessable()->assertJsonPath('data.code', 'system_role_locked');
});
