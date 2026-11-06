<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\People\Events\UserDeactivated;
use App\Domain\People\Models\HourlyRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

it('deactivates instead of deleting: no login, no tokens, out of the directory (RF-01.10)', function () {
    Event::fake([UserDeactivated::class]);
    $admin = adminUser();
    $person = userWithRole($admin);
    $person->createToken('laptop');

    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');

    expect($person->fresh()->status)->toBe(UserStatus::Inactive)
        ->and($person->tokens()->count())->toBe(0);
    Event::assertDispatched(UserDeactivated::class, fn ($event) => $event->user->is($person));

    $this->actingAs($admin)->getJson('/api/v1/people')->assertJsonMissing(['id' => $person->id]);
    $this->actingAs($admin)->getJson('/api/v1/people?include_inactive=1')->assertJsonFragment(['id' => $person->id]);
    $this->actingAs($person->fresh())->getJson('/api/v1/me')->assertUnauthorized();
});

it('never deactivates yourself or the last Admin', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson("/api/v1/people/{$admin->id}/deactivate")
        ->assertUnprocessable()->assertJsonPath('data.code', 'cannot_deactivate_self');
});

it('reactivates a person', function () {
    $admin = adminUser();
    $person = userWithRole($admin);
    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/deactivate");

    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
});

it('keeps rate history so past months use the old rate (CA-01.11)', function () {
    $admin = adminUser();
    $person = userWithRole($admin);

    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/rates", ['amount_cents' => 1000, 'valid_from' => '2026-01-01'])->assertCreated();
    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/rates", ['amount_cents' => 1200, 'valid_from' => '2026-10-01'])->assertCreated();
    $this->actingAs($admin)->postJson("/api/v1/people/{$person->id}/rates", ['amount_cents' => 900, 'valid_from' => '2026-05-01'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'rate_not_after_current');

    $rateOn = fn (string $day) => HourlyRate::query()->forCompany($admin->company_id)
        ->where('user_id', $person->id)->validOn(Carbon::parse($day))->value('amount_cents');

    expect($rateOn('2026-09-15'))->toBe(1000)
        ->and($rateOn('2026-10-01'))->toBe(1200);
    $this->actingAs($admin)->getJson("/api/v1/people/{$person->id}/rates")
        ->assertOk()->assertJsonPath('data.1.valid_to', '2026-09-30')->assertJsonPath('data.0.currency', 'ARS');
});

it('returns 403 when a collaborator touches rates, even their own (CA-01.09)', function () {
    $person = userWithRole(adminUser());

    $this->actingAs($person)->postJson("/api/v1/people/{$person->id}/rates", ['amount_cents' => 99999, 'valid_from' => '2026-01-01'])->assertForbidden();
    $this->actingAs($person)->getJson("/api/v1/people/{$person->id}/rates")->assertForbidden();
});
