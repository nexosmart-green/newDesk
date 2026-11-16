<?php

use Illuminate\Support\Carbon;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-11 15:00:00', 'UTC')));

it('filters the project hours tab by local dates: 23:30 belongs to that day, not the next (RF-05.11)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 100 * 3600);
    $timezone = 'America/Argentina/Buenos_Aires';
    $admin->forceFill(['timezone' => $timezone])->save();
    $late = logEntry($dev, $task, Carbon::parse('2026-11-09 23:30', $timezone), 1800); // next day in UTC west of Greenwich
    $nextMorning = logEntry($dev, $task, Carbon::parse('2026-11-10 09:00', $timezone), 3600);

    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/time-entries?from=2026-11-09&to=2026-11-09")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $late->id);

    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/time-entries?from=2026-11-10&to=2026-11-10")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $nextMorning->id);
});

it('rejects a range that ends before it starts, naming both fields', function () {
    $admin = adminUser();
    ['project' => $project] = bagProject($admin, [], 3600);

    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/time-entries?from=2026-11-10&to=2026-11-01")
        ->assertUnprocessable()
        ->assertJsonPath('errors.to.0', '«Hasta» no puede ser anterior a «Desde».');
});

it('lists the bag movements with who loaded them', function () {
    $admin = adminUser();
    ['project' => $project] = bagProject($admin, [], 3600);

    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/hours")
        ->assertOk()
        ->assertJsonPath('data.allocations.0.seconds', 3600)
        ->assertJsonPath('data.allocations.0.created_by', $admin->fullName());
});
