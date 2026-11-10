<?php

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\ShiftAssignment;

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

it('sets an arbitrary selection of cells atomically, all or nothing', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $template = $this->actingAs($admin)->postJson('/api/v1/shift-templates', ['name' => 'Mañana', 'starts_at' => '09:00', 'ends_at' => '13:00', 'color' => '#4F8CFF', 'mode' => 'remote'])->json('data.id');

    $this->actingAs($admin)->putJson('/api/v1/shifts/cells', ['shift_template_id' => $template, 'cells' => [
        ['user_id' => $ana->id, 'date' => '2026-11-09'],
        ['user_id' => $ana->id, 'date' => '2026-11-11'],
        ['user_id' => $admin->id, 'date' => '2026-11-10'],
    ]])->assertOk()->assertJsonPath('data.cells', 3);

    $gone = User::factory()->inactive()->forRole(roleNamed($admin, 'Colaborador'))->create();
    $this->actingAs($admin)->putJson('/api/v1/shifts/cells', ['shift_template_id' => null, 'cells' => [
        ['user_id' => $ana->id, 'date' => '2026-11-09'],
        ['user_id' => $gone->id, 'date' => '2026-11-09'],
    ]])->assertUnprocessable();

    expect(ShiftAssignment::query()->count())->toBe(3);
});

it('lists holidays by year and tells whether the iCal feed is active', function () {
    $admin = adminUser();
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-07-09', 'name' => 'Independencia', 'scope' => 'national']);
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2027-07-09', 'name' => 'Independencia', 'scope' => 'national']);

    $this->actingAs($admin)->getJson('/api/v1/holidays?year=2026')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($admin)->getJson('/api/v1/me/calendar-feed')->assertJsonPath('data.active', false);
    $this->actingAs($admin)->postJson('/api/v1/me/calendar-feed');
    $this->actingAs($admin)->getJson('/api/v1/me/calendar-feed')->assertJsonPath('data.active', true);
});

it('flags tasks of projects without hours so ▶ can explain before the click (RF-05.05)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    bagProject($admin, [$dev], 0, 'dry');
    bagProject($admin, [$dev], 3600, 'ok');

    $tasks = collect($this->actingAs($dev)->getJson('/api/v1/me/tasks')->assertOk()->json('data'))->keyBy('code');

    expect($tasks['dry-1']['project']['hours_exhausted'])->toBeTrue()
        ->and($tasks['ok-1']['project']['hours_exhausted'])->toBeFalse();
});
