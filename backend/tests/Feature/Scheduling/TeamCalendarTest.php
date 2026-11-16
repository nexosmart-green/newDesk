<?php

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

function teamTemplate(User $admin, array $data = []): int
{
    return test()->actingAs($admin)->postJson('/api/v1/shift-templates', [
        'name' => 'Completo', 'starts_at' => '09:00', 'ends_at' => '18:00', 'color' => '#309798', 'mode' => 'remote', ...$data,
    ])->assertCreated()->json('data.id');
}

function reportsTo(User $admin, User $person, User $lead): void
{
    test()->actingAs($admin)->putJson("/api/v1/people/{$person->id}", ['reports_to_user_id' => $lead->id])->assertOk();
}

it('shows who works each day to whoever manages shifts (RF-06.05)', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $gone = User::factory()->inactive()->forRole(roleNamed($admin, 'Colaborador'))->create();
    $template = teamTemplate($admin);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => $template, 'from' => '2026-11-16', 'to' => '2026-11-17'])->assertOk();
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-11-23', 'name' => 'Soberanía', 'scope' => 'national']);

    $data = $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-16&to=2026-11-23')->assertOk()->json('data');

    expect($data['timezone'])->toBe('America/Argentina/Buenos_Aires')
        ->and(collect($data['people'])->pluck('id')->all())->toEqualCanonicalizing([$admin->id, $ana->id])
        ->and(collect($data['people'])->pluck('id'))->not->toContain($gone->id)
        ->and($data['people'][0])->toHaveKeys(['id', 'full_name', 'avatar', 'job_title'])
        ->and($data['days'])->toHaveCount(8)
        ->and($data['days'][0]['date'])->toBe('2026-11-16')
        ->and($data['days'][0]['holiday'])->toBeNull()
        ->and($data['days'][7]['holiday'])->toBe(['name' => 'Soberanía', 'scope' => 'national'])
        ->and($data['days'][2]['shifts'])->toBe([]);

    expect($data['days'][0]['shifts'][0])->toMatchArray([
        'user_id' => $ana->id, 'starts_at' => '09:00', 'ends_at' => '18:00', 'mode' => 'remote', 'template_name' => 'Completo', 'color' => '#309798',
    ]);
});

it('shows a leader only themselves and their reports', function () {
    $admin = adminUser();
    $lead = userWithRole($admin);
    $dev = userWithRole($admin);
    $stranger = userWithRole($admin);
    reportsTo($admin, $dev, $lead);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', [
        'user_ids' => [$dev->id, $stranger->id], 'shift_template_id' => teamTemplate($admin), 'from' => '2026-11-16', 'to' => '2026-11-16',
    ])->assertOk();

    $data = $this->actingAs($lead)->getJson('/api/v1/team-calendar?from=2026-11-16&to=2026-11-22')->assertOk()->json('data');

    expect(collect($data['people'])->pluck('id')->all())->toEqualCanonicalizing([$lead->id, $dev->id])
        ->and(collect($data['days'][0]['shifts'])->pluck('user_id')->all())->toBe([$dev->id]);
});

it('is 403 for people without shifts.manage nor anyone in charge', function () {
    $admin = adminUser();
    $collaborator = userWithRole($admin);
    $leftReport = User::factory()->forRole(roleNamed($admin, 'Colaborador'))->create();
    reportsTo($admin, $leftReport, $collaborator);
    $leftReport->forceFill(['status' => 'inactive'])->save();

    $this->actingAs($collaborator)->getJson('/api/v1/team-calendar?from=2026-11-16&to=2026-11-22')->assertForbidden()
        ->assertJsonPath('message', 'El calendario de equipo es para quien gestiona turnos o tiene gente a cargo.');
});

it('limits the range to 31 days', function () {
    $admin = adminUser();

    $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-01&to=2026-12-01')->assertOk()->assertJsonCount(31, 'data.days');
    $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-01&to=2026-12-02')->assertUnprocessable()
        ->assertJsonPath('errors.to.0', 'Elegí un rango de hasta 31 días (pediste 32). Si necesitás más, consultalo por partes: un período a continuación del otro.');
    $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-10&to=2026-11-01')->assertUnprocessable()
        ->assertJsonPath('errors.to.0', 'La fecha de fin no puede ser anterior al inicio.');
});

it('never shows another company', function () {
    $admin = adminUser();
    $outsider = adminUser('otra@example.com');
    $this->actingAs($outsider)->postJson('/api/v1/shifts/assign', ['user_ids' => [$outsider->id], 'shift_template_id' => teamTemplate($outsider), 'from' => '2026-11-16', 'to' => '2026-11-16']);

    $data = $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-16&to=2026-11-16')->assertOk()->json('data');

    expect(collect($data['people'])->pluck('id')->all())->toBe([$admin->id])
        ->and($data['days'][0]['shifts'])->toBe([]);
});

it('runs a constant number of queries whatever the team size (no N+1)', function () {
    $admin = adminUser();
    $template = teamTemplate($admin);
    $queries = function () use ($admin) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/v1/team-calendar?from=2026-11-16&to=2026-11-22')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $staff = fn (int $count) => collect(range(1, $count))->map(fn () => userWithRole($admin))->each(fn (User $person) => reportsTo($admin, $person, $admin));
    $assign = fn ($people) => $this->actingAs($admin)->postJson('/api/v1/shifts/assign', [
        'user_ids' => $people->pluck('id')->all(), 'shift_template_id' => $template, 'from' => '2026-11-16', 'to' => '2026-11-22',
    ])->assertOk();

    $assign($staff(2));
    $few = $queries();
    $assign($staff(6));

    expect($queries())->toBe($few);
});
