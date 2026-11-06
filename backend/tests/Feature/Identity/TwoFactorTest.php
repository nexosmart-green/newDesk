<?php

use App\Domain\Identity\Models\User;

beforeEach(fn () => $this->withHeaders(['Referer' => 'http://localhost:5173', 'Origin' => 'http://localhost:5173']));

it('asks for the second step when the user has 2FA and logs in with a valid code', function () {
    adminUser('ada@example.com');

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertOk()->assertJsonPath('data.two_factor_required', true);
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentTotp()])
        ->assertOk()->assertJsonPath('data.email', 'ada@example.com');
    $this->getJson('/api/v1/me')->assertOk();
});

it('rejects a wrong code and a replayed code', function () {
    adminUser('ada@example.com');
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password']);

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();

    $code = currentTotp();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/logout');
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password']);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])->assertUnprocessable();
});

it('accepts a recovery code only once', function () {
    $admin = adminUser('ada@example.com');
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password']);

    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'recov-ery01'])->assertOk();

    expect($admin->fresh()->two_factor_recovery_codes)->toBe([]);
});

it('blocks an Admin without 2FA from everything but /me and the setup (RF-01.02)', function () {
    $admin = provisionCompany();

    $this->actingAs($admin)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.two_factor.required', true);
    $this->actingAs($admin)->getJson('/api/v1/roles')
        ->assertForbidden()->assertJsonPath('data.code', 'two_factor_setup_required');
});

it('enables 2FA with a confirmed code and returns recovery codes once', function () {
    $admin = provisionCompany();
    $this->actingAs($admin);

    $secret = $this->postJson('/api/v1/me/two-factor')->assertOk()->json('data.secret');
    $this->postJson('/api/v1/me/two-factor/confirm', ['code' => '000000'])->assertUnprocessable();

    $this->postJson('/api/v1/me/two-factor/confirm', ['code' => currentTotp($secret)])
        ->assertOk()->assertJsonCount(8, 'data.recovery_codes');

    $this->getJson('/api/v1/roles')->assertOk();
    expect(User::query()->find($admin->id)->getRawOriginal('two_factor_secret'))->not->toBe($secret);
});

it('does not let an Admin turn 2FA off', function () {
    $admin = adminUser();

    $this->actingAs($admin)->deleteJson('/api/v1/me/two-factor', ['password' => 'password'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'two_factor_required');
});

it('lets a collaborator work without 2FA', function () {
    $collaborator = userWithRole(provisionCompany());

    $this->actingAs($collaborator)->getJson('/api/v1/company')->assertOk();
});
