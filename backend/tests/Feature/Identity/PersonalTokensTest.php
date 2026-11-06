<?php

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * RF-12.02 (minimal) and monitoreo's /api/tokens (D-050): each user's own MCP tokens. Shown once, kept
 * hashed, listed with their last use, revoked in one call.
 */
beforeEach(function () {
    $this->admin = Reports::signIn(provisionCompany('admin@nexosmart.com.ar'));
});

it('creates a token shown once, keeps only its hash and lists it without it (RF-12.02, CA-12.02)', function () {
    $created = $this->postJson('/api/v1/me/tokens', ['name' => "  Claude de Iña\u{0007} ", 'expires_in_days' => 30])->assertCreated()
        ->assertJsonPath('message', 'Token creado. Copialo ahora: no se vuelve a mostrar.')
        ->assertJsonPath('data.name', 'Claude de Iña')->assertJsonPath('data.last_used_at', null)->json('data');
    [$id, $secret] = explode('|', $created['token'], 2);
    expect((int) $id)->toBe($created['id'])->and($secret)->toStartWith('nd_')
        ->and(CarbonImmutable::parse($created['expires_at'])->diffInDays(now()->addDays(30), true))->toBeLessThan(1);
    expect(DB::table('personal_access_tokens')->where('id', $id)->value('token'))->toBe(hash('sha256', $secret));
    $this->postJson('/api/v1/me/tokens', ['name' => 'Sin vencimiento'])->assertCreated()->assertJsonPath('data.expires_at', null);

    $list = $this->getJson('/api/v1/me/tokens')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Sin vencimiento')->assertJsonPath('data.1.name', 'Claude de Iña');
    expect($list->getContent())->not->toContain($secret)->not->toContain('"token"');
    expect(ActivityLog::query()->where('event', 'token.created')->where('actor_id', $this->admin->id)->count())->toBe(2);
});

it('checks the name and the expiry', function () {
    $this->postJson('/api/v1/me/tokens', ['name' => '   '])->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Poné un nombre (hasta 80 caracteres), por ejemplo "Claude de Iña".');
    $this->postJson('/api/v1/me/tokens', ['name' => str_repeat('a', 81)])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/me/tokens', ['name' => 'x', 'expires_in_days' => 7])->assertUnprocessable()
        ->assertJsonPath('errors.expires_in_days.0', 'Elegí 30, 90 o 365 días, o sin vencimiento.');
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes only the user\'s own tokens', function () {
    $id = $this->postJson('/api/v1/me/tokens', ['name' => 'Claude'])->json('data.id');
    $other = Reports::signIn(User::factory()->create(['company_id' => $this->admin->company_id, 'role_id' => $this->admin->role_id]));
    $this->deleteJson("/api/v1/me/tokens/{$id}")->assertNotFound()->assertJsonPath('message', 'Token no encontrado.');
    $this->getJson('/api/v1/me/tokens')->assertOk()->assertJsonCount(0, 'data');

    Reports::signIn($this->admin);
    $this->deleteJson("/api/v1/me/tokens/{$id}")->assertOk()->assertJsonPath('message', 'Token revocado.');
    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('event', 'token.revoked')->value('before'))->toBe(['token_id' => $id, 'name' => 'Claude'])
        ->and($other->tokens()->count())->toBe(0);
});

it('is only for whoever may use MCP: never a client user', function () {
    Reports::signIn(User::factory()->withPermissions([])->create(['company_id' => $this->admin->company_id]));
    $this->getJson('/api/v1/me/tokens')->assertForbidden();
    $this->postJson('/api/v1/me/tokens', ['name' => 'x'])->assertForbidden();

    Reports::signIn(Reports::clientUser(Fixtures::project($this->admin), 'dueno@playinn.com'));
    $this->postJson('/api/v1/me/tokens', ['name' => 'x'])->assertForbidden();
});
