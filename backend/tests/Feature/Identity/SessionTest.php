<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', 'http://localhost:5173')->withHeader('Origin', 'http://localhost:5173');
});

it('logs an active user in and returns the current user with permissions', function () {
    provisionCompany('ada@example.com');

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.role.name', DefaultRoles::ADMIN)
        ->assertJsonPath('data.company.currency', 'ARS')
        ->assertJsonPath('errors', null);

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.full_name', 'Ada Admin');
});

it('rejects wrong credentials with a field error', function () {
    provisionCompany('ada@example.com');

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El email o la contraseña no coinciden. Revisalos o usá «¿Olvidaste tu contraseña?».');
});

it('does not let a deactivated user log in (RF-01.10)', function () {
    $user = User::factory()->inactive()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
});

it('cuts access immediately when an authenticated user is deactivated', function () {
    $user = User::factory()->create();
    $user->forceFill(['status' => 'inactive'])->save();

    $this->actingAs($user)->getJson('/api/v1/me')->assertUnauthorized();
});

it('throttles repeated login attempts', function () {
    provisionCompany('ada@example.com');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope'])->assertTooManyRequests();
});

it('evaluates permissions against the current role on every request (RF-12.03)', function () {
    $admin = provisionCompany();
    $collaborator = User::factory()->forRole(roleNamed($admin, DefaultRoles::COLLABORATOR))->create();

    expect($collaborator->can(Permission::HoursAllocate->value))->toBeFalse();

    $collaborator->update(['role_id' => roleNamed($admin, DefaultRoles::MANAGER)->id]);

    expect($collaborator->fresh()->can(Permission::HoursAllocate->value))->toBeTrue();
});
