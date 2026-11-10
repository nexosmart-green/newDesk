<?php

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Notifications\ShiftChangedNotification;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

function createTemplate(User $admin, array $data = []): int
{
    return test()->actingAs($admin)->postJson('/api/v1/shift-templates', [
        'name' => 'Completo 9–18', 'starts_at' => '09:00', 'ends_at' => '18:00', 'color' => '#4F8CFF', 'mode' => 'office', ...$data,
    ])->assertCreated()->json('data.id');
}

it('creates templates and assigns them in bulk by range and weekdays (RF-06.01/02)', function () {
    Notification::fake();
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    $template = createTemplate($admin);

    // Mon 2026-11-09 .. Sun 2026-11-15, Mon–Fri only.
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', [
        'user_ids' => [$ana->id, $beto->id], 'shift_template_id' => $template,
        'from' => '2026-11-09', 'to' => '2026-11-15', 'weekdays' => [1, 2, 3, 4, 5],
    ])->assertOk()->assertJsonPath('data.cells', 10);

    $grid = $this->actingAs($admin)->getJson('/api/v1/shifts?from=2026-11-09&to=2026-11-15')->assertOk()->json('data.people');
    expect(collect($grid)->firstWhere('id', $ana->id)['shifts'])->toHaveCount(5);

    Notification::assertSentToTimes($ana, ShiftChangedNotification::class, 1);
    Notification::assertNotSentTo($admin, ShiftChangedNotification::class);
});

it('replaces the day when reassigning and clears it with no template', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $full = createTemplate($admin);
    $morning = createTemplate($admin, ['name' => 'Mañana 9–13', 'ends_at' => '13:00', 'mode' => 'remote']);
    $assign = fn (?int $template) => $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => $template, 'from' => '2026-11-09', 'to' => '2026-11-09']);

    $assign($full);
    $assign($morning);
    expect(ShiftAssignment::query()->where('user_id', $ana->id)->get())->toHaveCount(1)->first()->mode->value->toBe('remote');

    $assign(null);
    expect(ShiftAssignment::query()->where('user_id', $ana->id)->count())->toBe(0);
});

it('copies the previous week (RF-06.02)', function () {
    $admin = adminUser();
    $ana = userWithRole($admin);
    $template = createTemplate($admin);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$ana->id], 'shift_template_id' => $template, 'from' => '2026-11-02', 'to' => '2026-11-06']);

    $this->actingAs($admin)->postJson('/api/v1/shifts/copy-previous-week', ['week_start' => '2026-11-09'])->assertOk()->assertJsonPath('data.copied', 5);

    expect(ShiftAssignment::query()->whereBetween('date', ['2026-11-09', '2026-11-15'])->count())->toBe(5);
});

it('shows "Mi calendario" with shifts, my due tasks and holidays (RF-06.04/06)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Entrega', 'due_on' => '2026-11-10', 'assignee_ids' => [$dev->id]]);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$dev->id], 'shift_template_id' => createTemplate($admin), 'from' => '2026-11-10', 'to' => '2026-11-10']);
    $this->actingAs($admin)->postJson('/api/v1/holidays', ['date' => '2026-11-23', 'name' => 'Día de la Soberanía', 'scope' => 'national'])->assertCreated();

    $this->actingAs($dev)->getJson('/api/v1/me/calendar?from=2026-11-01&to=2026-11-30')
        ->assertOk()
        ->assertJsonPath('data.shifts.0.start_utc', '2026-11-10T12:00:00+00:00') // 09:00 Buenos Aires
        ->assertJsonPath('data.tasks_due.0.code', 'bhn-2')
        ->assertJsonPath('data.holidays.0.name', 'Día de la Soberanía');
});

it('only shifts.manage builds the grid', function () {
    $collaborator = userWithRole(adminUser());

    $this->actingAs($collaborator)->getJson('/api/v1/shifts?from=2026-11-09&to=2026-11-15')->assertForbidden();
    $this->actingAs($collaborator)->postJson('/api/v1/shift-templates', ['name' => 'X', 'starts_at' => '09:00', 'ends_at' => '10:00', 'color' => '#000000', 'mode' => 'office'])->assertForbidden();
});

it('serves a personal, revocable iCal feed with only the owner data (RF-06.07, CA-06.07)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$dev->id, $admin->id], 'shift_template_id' => createTemplate($admin), 'from' => today()->toDateString(), 'to' => today()->toDateString()]);

    $url = $this->actingAs($dev)->postJson('/api/v1/me/calendar-feed')->assertCreated()->json('data.url');
    $path = parse_url($url, PHP_URL_PATH);

    $ics = $this->get($path)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
    expect($ics)->toContain('BEGIN:VCALENDAR')->toContain($dev->fullName())->not->toContain($admin->fullName())
        ->and(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1);

    $this->actingAs($dev)->deleteJson('/api/v1/me/calendar-feed')->assertOk();
    $this->get($path)->assertNotFound();
});

it('cuts a forgotten timer one hour after the shift ends (P-07, RF-05.10)', function () {
    $this->travelTo(Carbon::parse('2026-11-10 09:00', 'America/Argentina/Buenos_Aires'));
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 50 * 3600);
    $this->actingAs($admin)->postJson('/api/v1/shifts/assign', ['user_ids' => [$dev->id], 'shift_template_id' => createTemplate($admin, ['ends_at' => '13:00']), 'from' => '2026-11-10', 'to' => '2026-11-10']);
    startTimer($dev, $task)->assertOk();

    $this->travelTo(Carbon::parse('2026-11-10 14:30', 'America/Argentina/Buenos_Aires'));
    $this->artisan('timers:cut');

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->stop_reason->value)->toBe('forgotten')->and($entry->seconds)->toBe(5 * 3600); // 09:00 → 13:00 + 1 h
});
