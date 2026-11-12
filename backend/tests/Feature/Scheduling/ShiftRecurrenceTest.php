<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Notifications\ShiftChangedNotification;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Shared\Activity\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const RECURRENCE_TZ = 'America/Argentina/Buenos_Aires';

/** Thursday 2026-11-12, 23:30 in Buenos Aires = already Friday in UTC: "today" must be the company's. */
function travelToLateThursday(): void
{
    test()->travelTo(Carbon::parse('2026-11-12 23:30', RECURRENCE_TZ));
}

function recurrenceTemplate(User $admin, array $data = []): int
{
    return test()->actingAs($admin)->postJson('/api/v1/shift-templates', [
        'name' => 'Completo', 'starts_at' => '09:00', 'ends_at' => '18:00', 'color' => '#309798', 'mode' => 'office', ...$data,
    ])->assertCreated()->json('data.id');
}

function createRecurrence(User $admin, array $userIds, int $templateId, array $data = []): TestResponse
{
    return test()->actingAs($admin)->postJson('/api/v1/shift-recurrences', [
        'user_ids' => $userIds, 'shift_template_id' => $templateId, 'weekdays' => [1, 2, 3, 4, 5], 'starts_on' => '2026-11-12', ...$data,
    ]);
}

/** @return list<string> */
function shiftDates(User $person): array
{
    return ShiftAssignment::query()->withoutGlobalScopes()->where('user_id', $person->id)->orderBy('date')
        ->pluck('date')->map(fn ($date) => $date->toDateString())->all();
}

it('generates the days up to 8 weeks ahead in the company timezone, skipping holidays and taken days (RF-06.03)', function () {
    Notification::fake();
    travelToLateThursday();
    $admin = adminUser();
    $ana = userWithRole($admin);
    $template = recurrenceTemplate($admin);
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-11-23', 'name' => 'Soberanía', 'scope' => 'national'])->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-12-07', 'name' => 'Cierre anual', 'scope' => 'company'])->assertCreated();
    $morning = recurrenceTemplate($admin, ['name' => 'Mañana', 'ends_at' => '13:00']);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => $morning, 'from' => '2026-11-13', 'to' => '2026-11-13'])->assertOk();
    Notification::fake();

    // Thu 12/11 .. Thu 07/01 (today + 8 weeks): 41 weekdays − 2 holidays − 1 taken = 38.
    $response = createRecurrence($admin, [$ana->id], $template)->assertCreated()
        ->assertJsonPath('data.0.generated', 38)
        ->assertJsonPath('data.0.skipped_count', 3)
        ->assertJsonPath('data.0.recurrence.user.id', $ana->id)
        ->assertJsonPath('data.0.recurrence.weekdays', [1, 2, 3, 4, 5])
        ->assertJsonPath('data.0.recurrence.template.name', 'Completo')
        ->assertJsonPath('data.0.recurrence.ends_on', null)
        ->assertJsonPath('message', 'Turno fijo creado: se generaron 38 días (se saltearon 2 feriados y 1 día que ya tenía turno).');

    expect($response->json('data.0.skipped'))->toEqual([
        ['date' => '2026-11-13', 'reason' => 'taken'],
        ['date' => '2026-11-23', 'reason' => 'holiday'],
        ['date' => '2026-12-07', 'reason' => 'holiday'],
    ]);
    $dates = shiftDates($ana);
    expect($dates)->toHaveCount(39) // + the manual one
        ->and($dates[0])->toBe('2026-11-12')
        ->and(end($dates))->toBe('2027-01-07')
        ->and(ShiftAssignment::query()->where('date', '2026-11-13')->value('ends_at'))->toBe('13:00:00')
        ->and(ShiftAssignment::query()->whereNotNull('recurrence_id')->count())->toBe(38);

    Notification::assertSentToTimes($ana, ShiftChangedNotification::class, 1);
    Notification::assertNotSentTo($admin, ShiftChangedNotification::class);
    expect(ActivityLog::query()->where('event', 'shift_recurrence.created')->count())->toBe(1);
});

it('accepts today in the company timezone and rejects yesterday', function () {
    travelToLateThursday();
    $admin = adminUser();
    $template = recurrenceTemplate($admin);

    createRecurrence($admin, [$admin->id], $template, ['starts_on' => '2026-11-11'])->assertUnprocessable()
        ->assertJsonPath('errors.starts_on.0', 'El turno fijo no puede empezar antes de hoy.');
    createRecurrence($admin, [$admin->id], $template, ['starts_on' => '2026-11-12'])->assertCreated();
});

it('generates holidays when skip_holidays is off and stops at ends_on', function () {
    travelToLateThursday();
    $admin = adminUser();
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-11-23', 'name' => 'Soberanía', 'scope' => 'national']);

    createRecurrence($admin, [$admin->id], recurrenceTemplate($admin), ['weekdays' => [1], 'skip_holidays' => false, 'ends_on' => '2026-11-30'])
        ->assertCreated()->assertJsonPath('data.0.generated', 3)->assertJsonPath('data.0.recurrence.skip_holidays', false);

    expect(shiftDates($admin))->toBe(['2026-11-16', '2026-11-23', '2026-11-30']);
});

it('creates one rule per person and notifies each once', function () {
    Notification::fake();
    travelToLateThursday();
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);

    createRecurrence($admin, [$ana->id, $beto->id], recurrenceTemplate($admin), ['weekdays' => [6, 7]])
        ->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.generated', 16);

    expect(ShiftRecurrence::query()->count())->toBe(2);
    Notification::assertSentToTimes($ana, ShiftChangedNotification::class, 1);
    Notification::assertSentToTimes($beto, ShiftChangedNotification::class, 1);
});

it('keeps the 8-week horizon with an idempotent daily command that never refills days freed by hand', function () {
    travelToLateThursday();
    $admin = adminUser();
    createRecurrence($admin, [$admin->id], recurrenceTemplate($admin))->assertCreated();
    expect(shiftDates($admin))->toHaveCount(41);

    $this->artisan('shifts:extend-recurrences')->assertSuccessful();
    expect(shiftDates($admin))->toHaveCount(41);

    // A week later the horizon moves to 14/01: five more weekdays.
    $this->travelTo(Carbon::parse('2026-11-19 10:00', RECURRENCE_TZ));
    $this->actingAs($admin)->putJson('/api/v1/shifts/cells', ['shift_template_id' => null, 'cells' => [['user_id' => $admin->id, 'date' => '2026-11-20']]])->assertOk();
    $this->artisan('shifts:extend-recurrences')->assertSuccessful();
    $this->artisan('shifts:extend-recurrences')->assertSuccessful();

    $dates = shiftDates($admin);
    expect($dates)->toHaveCount(45)->not->toContain('2026-11-20')
        ->and(end($dates))->toBe('2027-01-14');
});

it('stops generating for people who left and for deleted templates', function () {
    travelToLateThursday();
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    $template = recurrenceTemplate($admin);
    $other = recurrenceTemplate($admin, ['name' => 'Tarde', 'starts_at' => '14:00', 'ends_at' => '20:00']);
    createRecurrence($admin, [$ana->id], $template)->assertCreated();
    createRecurrence($admin, [$beto->id], $other)->assertCreated();

    $ana->forceFill(['status' => UserStatus::Inactive])->save();
    $this->actingAs($admin)->deleteJson("/api/v1/shift-templates/{$other}")->assertOk();
    $this->travelTo(Carbon::parse('2026-11-26 10:00', RECURRENCE_TZ));
    $this->artisan('shifts:extend-recurrences')->assertSuccessful();

    expect(shiftDates($ana))->toHaveCount(41)->and(shiftDates($beto))->toHaveCount(41);
    $list = collect($this->actingAs($admin)->getJson('/api/v1/shift-recurrences')->assertOk()->json('data'))->keyBy('user.id');
    expect($list[$beto->id]['template'])->toBeNull()
        ->and($list[$ana->id]['template']['name'])->toBe('Completo');
});

it('ends a rule removing only its days after the end date (RF-06.03)', function () {
    Notification::fake();
    travelToLateThursday();
    $admin = adminUser();
    $ana = userWithRole($admin);
    $id = createRecurrence($admin, [$ana->id], recurrenceTemplate($admin))->json('data.0.recurrence.id');
    $this->travelTo(Carbon::parse('2026-11-20 10:00', RECURRENCE_TZ));
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => recurrenceTemplate($admin, ['name' => 'Sábado']), 'from' => '2026-11-28', 'to' => '2026-11-28']);
    Notification::fake();

    $this->actingAs($admin)->putJson("/api/v1/shift-recurrences/{$id}/end", ['ends_on' => '2026-11-19'])->assertUnprocessable()
        ->assertJsonPath('message', 'La fecha de fin no puede ser anterior a hoy.');

    $this->actingAs($admin)->putJson("/api/v1/shift-recurrences/{$id}/end", ['ends_on' => '2026-11-27'])->assertOk()
        ->assertJsonPath('data.recurrence.ends_on', '2026-11-27')
        ->assertJsonPath('data.removed', 29); // 41 generated − 12 up to Fri 27/11

    expect(shiftDates($ana))->toHaveCount(13)->toContain('2026-11-12', '2026-11-27', '2026-11-28')
        ->and(ActivityLog::query()->where('event', 'shift_recurrence.ended')->count())->toBe(1);
    Notification::assertSentToTimes($ana, ShiftChangedNotification::class, 1);

    // The command respects the end date.
    $this->travelTo(Carbon::parse('2026-11-25 10:00', RECURRENCE_TZ));
    $this->artisan('shifts:extend-recurrences');
    expect(shiftDates($ana))->toHaveCount(13);
});

it('deletes a rule: past days stay as plain shifts, future ones go away', function () {
    travelToLateThursday();
    $admin = adminUser();
    $id = createRecurrence($admin, [$admin->id], recurrenceTemplate($admin))->json('data.0.recurrence.id');
    $this->travelTo(Carbon::parse('2026-11-18 10:00', RECURRENCE_TZ));

    $this->actingAs($admin)->deleteJson("/api/v1/shift-recurrences/{$id}")->assertOk()
        ->assertJsonPath('data.removed', 36); // keeps 12, 13, 16, 17, 18

    expect(ShiftRecurrence::query()->count())->toBe(0)
        ->and(shiftDates($admin))->toBe(['2026-11-12', '2026-11-13', '2026-11-16', '2026-11-17', '2026-11-18'])
        ->and(ShiftAssignment::query()->whereNotNull('recurrence_id')->count())->toBe(0)
        ->and(ActivityLog::query()->where('event', 'shift_recurrence.deleted')->count())->toBe(1);
});

it('validates the rule with clear messages', function () {
    travelToLateThursday();
    $admin = adminUser();
    $template = recurrenceTemplate($admin);
    $gone = User::factory()->inactive()->forRole(roleNamed($admin, 'Colaborador'))->create();

    createRecurrence($admin, [$admin->id], $template, ['weekdays' => []])->assertUnprocessable()
        ->assertJsonPath('errors.weekdays.0', 'Elegí al menos un día de la semana.');
    expect(createRecurrence($admin, [$admin->id], $template, ['weekdays' => [1, 1]])->assertUnprocessable()->json('errors')['weekdays.1'])
        ->toContain('Repetiste un día de la semana.');
    expect(createRecurrence($admin, [$admin->id], $template, ['weekdays' => [8]])->assertUnprocessable()->json('errors')['weekdays.0'])
        ->toContain('Los días van del lunes (1) al domingo (7).');
    createRecurrence($admin, [$admin->id], $template, ['ends_on' => '2026-11-10'])->assertUnprocessable()
        ->assertJsonPath('errors.ends_on.0', 'La fecha de fin no puede ser anterior al inicio.');
    createRecurrence($admin, [], $template)->assertUnprocessable()
        ->assertJsonPath('errors.user_ids.0', 'Elegí al menos una persona.');
    createRecurrence($admin, [$admin->id, $gone->id], $template)->assertUnprocessable()
        ->assertJsonPath('message', 'Alguna persona no existe o está dada de baja.');

    $outsider = adminUser('otra@example.com');
    createRecurrence($admin, [$admin->id], recurrenceTemplate($outsider))->assertUnprocessable()
        ->assertJsonPath('errors.shift_template_id.0', 'Esa plantilla no existe o fue borrada.');
    createRecurrence($admin, [$outsider->id], $template)->assertUnprocessable();

    expect(ShiftRecurrence::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejects ending a rule before it starts', function () {
    travelToLateThursday();
    $admin = adminUser();
    $id = createRecurrence($admin, [$admin->id], recurrenceTemplate($admin), ['starts_on' => '2026-12-01'])->json('data.0.recurrence.id');

    $this->actingAs($admin)->putJson("/api/v1/shift-recurrences/{$id}/end", ['ends_on' => '2026-11-20'])->assertUnprocessable()
        ->assertJsonPath('message', 'Este turno fijo empieza el 01/12/2026: si ya no lo querés, borralo.');
});

it('is only for shifts.manage, and another company rule is 404', function () {
    travelToLateThursday();
    $admin = adminUser();
    $collaborator = userWithRole($admin);
    $id = createRecurrence($admin, [$admin->id], recurrenceTemplate($admin))->json('data.0.recurrence.id');

    $this->actingAs($collaborator)->getJson('/api/v1/shift-recurrences')->assertForbidden();
    createRecurrence($collaborator, [$collaborator->id], 1)->assertForbidden();
    $this->actingAs($collaborator)->putJson("/api/v1/shift-recurrences/{$id}/end", ['ends_on' => '2026-12-01'])->assertForbidden();
    $this->actingAs($collaborator)->deleteJson("/api/v1/shift-recurrences/{$id}")->assertForbidden();

    $outsider = adminUser('otra@example.com');
    $this->actingAs($outsider)->getJson('/api/v1/shift-recurrences')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($outsider)->putJson("/api/v1/shift-recurrences/{$id}/end", ['ends_on' => '2026-12-01'])->assertNotFound();
    $this->actingAs($outsider)->deleteJson("/api/v1/shift-recurrences/{$id}")->assertNotFound();
    expect(ShiftRecurrence::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('filters the list by person', function () {
    travelToLateThursday();
    $admin = adminUser();
    $ana = userWithRole($admin);
    createRecurrence($admin, [$admin->id, $ana->id], recurrenceTemplate($admin))->assertCreated();

    $this->actingAs($admin)->getJson("/api/v1/shift-recurrences?user_id={$ana->id}")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.full_name', $ana->fullName());
});
