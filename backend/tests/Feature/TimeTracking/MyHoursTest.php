<?php

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;

require_once __DIR__.'/TimeTrackingHelpers.php';

/** An hour (or the given range) of manual time, `$daysAgo` local days back. */
function myHoursEntry(User $user, Task $task, int $daysAgo = 1, string $from = '09:00', string $to = '10:00'): int
{
    return test()->actingAs($user)->postJson("/api/v1/tasks/{$task->code}/time-entries", [
        'date' => today('America/Argentina/Buenos_Aires')->subDays($daysAgo)->toDateString(),
        'from' => $from, 'to' => $to, 'comment' => 'Trabajo del cliente',
    ])->assertCreated()->json('data.id');
}

function moveEntry(User $actor, int $entryId, Task $task)
{
    return test()->actingAs($actor)->putJson("/api/v1/time-entries/{$entryId}", ['task_id' => $task->id, 'comment' => 'Lo cargué en el proyecto equivocado']);
}

// CA-05.20a/c
it('lists only my entries from every project, newest first, with the window for each viewer', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $bhn] = bagProject($admin, [$dev, $mate], 10 * 3600);
    ['task' => $cdos] = bagProject($admin, [$dev], 10 * 3600, 'cdos');
    $older = myHoursEntry($dev, $cdos, 2);
    $newer = myHoursEntry($dev, $bhn, 1);
    myHoursEntry($mate, $bhn, 1);

    $page = $this->actingAs($dev)->getJson('/api/v1/time-entries')->assertOk();
    expect(array_column($page->json('data'), 'id'))->toBe([$newer, $older])
        ->and($page->json('data.1.project.name'))->toBe('CDOS')
        ->and($page->json('meta.per_page'))->toBe(50)
        ->and($page->json('meta.editable_since'))->toStartWith(today('America/Argentina/Buenos_Aires')->subDays(7)->toDateString());

    $this->actingAs($admin)->getJson('/api/v1/time-entries')->assertOk()
        ->assertJsonCount(0, 'data')->assertJsonPath('meta.editable_since', null);
});

// CA-05.20b
it('filters by project and by days of my timezone, also in the project tab', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $bhn, 'project' => $bhnProject] = bagProject($admin, [$dev], 10 * 3600);
    ['task' => $cdos, 'project' => $cdosProject] = bagProject($admin, [$dev], 10 * 3600, 'cdos');
    $late = myHoursEntry($dev, $bhn, 1, '23:30', '23:50'); // 02:30 UTC of the next day
    myHoursEntry($dev, $cdos, 1);
    $yesterday = today('America/Argentina/Buenos_Aires')->subDay()->toDateString();
    $today = today('America/Argentina/Buenos_Aires')->toDateString();

    $this->actingAs($dev)->getJson("/api/v1/time-entries?project_id={$bhnProject->id}&from={$yesterday}&to={$yesterday}")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $late);
    $this->actingAs($dev)->getJson("/api/v1/time-entries?from={$today}&to={$today}")->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($dev)->getJson("/api/v1/time-entries?project_id={$cdosProject->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson("/api/v1/projects/{$bhnProject->id}/time-entries?from={$yesterday}&to={$yesterday}")
        ->assertOk()->assertJsonPath('data.0.id', $late)->assertJsonPath('meta.editable_since', null);
});

// CA-05.20g
it('shows another person\'s hours only to time.manage', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $task] = bagProject($admin, [$dev, $mate], 10 * 3600);
    $entry = myHoursEntry($dev, $task);

    $this->actingAs($mate)->getJson("/api/v1/time-entries?user_id={$dev->id}")->assertForbidden();
    $this->actingAs($admin)->getJson("/api/v1/time-entries?user_id={$dev->id}")->assertOk()->assertJsonPath('data.0.id', $entry);
});

// CA-05.20d, R-7
it('moves an entry to a task of another project: both bags change and both projects log it', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $bhn, 'project' => $bhnProject] = bagProject($admin, [$dev], 10 * 3600);
    ['task' => $cdos, 'project' => $cdosProject] = bagProject($admin, [$dev], 10 * 3600, 'cdos');
    $entry = myHoursEntry($dev, $cdos);

    moveEntry($dev, $entry, $bhn)->assertOk()
        ->assertJsonPath('data.project.id', $bhnProject->id)->assertJsonPath('data.task.code', $bhn->code);

    expect($cdosProject->fresh()->consumed_seconds)->toBe(0)
        ->and($bhnProject->fresh()->consumed_seconds)->toBe(3600)
        ->and(ActivityLog::query()->where('event', 'time.corrected')->pluck('project_id')->all())
        ->toEqualCanonicalizing([$bhnProject->id, $cdosProject->id]);
    $this->artisan('hours:reconcile', ['--dry-run' => true])->assertSuccessful();
});

// CA-05.20e
it('refuses moves that do not fit, to tasks that are not the owner\'s or are closed, changing nothing', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $bhn, 'project' => $bhnProject] = bagProject($admin, [$dev, $mate], 10 * 3600);
    ['task' => $small] = bagProject($admin, [$dev], 1800, 'chico');
    ['task' => $closedProjectTask, 'project' => $closedProject] = bagProject($admin, [$dev], 10 * 3600, 'tres');
    $closedProject->forceFill(['status' => ProjectStatus::Finished])->save();
    $theirs = app(CreateTask::class)($admin, $bhnProject, ['title' => 'Ajena'], [$mate->id]);
    $done = app(CreateTask::class)($admin, $bhnProject, ['title' => 'Terminada'], [$dev->id]);
    $done->forceFill(['completed_at' => now()])->save();
    $entry = myHoursEntry($dev, $bhn);

    moveEntry($dev, $entry, $small)->assertUnprocessable()->assertJsonPath('data.code', 'manual_exceeds_bag');
    moveEntry($dev, $entry, $theirs)->assertUnprocessable()->assertJsonPath('data.code', 'task_not_assigned');
    moveEntry($dev, $entry, $done)->assertUnprocessable()->assertJsonPath('data.code', 'task_closed');
    moveEntry($dev, $entry, $closedProjectTask)->assertUnprocessable()->assertJsonPath('data.code', 'project_closed');

    expect(TimeEntry::query()->find($entry)->task_id)->toBe($bhn->id)
        ->and($bhnProject->fresh()->consumed_seconds)->toBe(3600);
});

// CA-05.20g
it('lets an admin move someone else\'s entry to a task of that person', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $bhn] = bagProject($admin, [$dev], 10 * 3600);
    ['task' => $cdos, 'project' => $cdosProject] = bagProject($admin, [$dev], 10 * 3600, 'cdos');
    $entry = myHoursEntry($dev, $bhn);

    moveEntry($admin, $entry, $cdos)->assertOk()->assertJsonPath('data.user.id', $dev->id);
    expect($cdosProject->fresh()->consumed_seconds)->toBe(3600);
});

// CA-05.20f, RF-05.15/17
it('lets people delete their own entries inside the window, explained, and never someone else\'s', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev, $mate], 10 * 3600);
    $mine = myHoursEntry($dev, $task);
    $old = myHoursEntry($dev, $task, 2);
    $pending = myHoursEntry($dev, $task, 3);
    $theirs = myHoursEntry($mate, $task);
    TimeEntry::query()->whereKey($old)->update(['started_at' => now()->subDays(10), 'ended_at' => now()->subDays(10)->addHour()]);
    TimeEntry::query()->whereKey($pending)->update(['comment_pending' => true]);

    $this->actingAs($dev)->deleteJson("/api/v1/time-entries/{$mine}")->assertOk();
    $this->actingAs($dev)->deleteJson("/api/v1/time-entries/{$old}")->assertUnprocessable()->assertJsonPath('data.code', 'outside_manual_window');
    $this->actingAs($dev)->deleteJson("/api/v1/time-entries/{$pending}")->assertUnprocessable()->assertJsonPath('data.code', 'comment_pending');
    $this->actingAs($dev)->deleteJson("/api/v1/time-entries/{$theirs}")->assertNotFound();

    expect($project->fresh()->consumed_seconds)->toBe(3 * 3600)
        ->and(ActivityLog::query()->where('event', 'time.deleted')->count())->toBe(1);
});

// CA-05.20h
it('adds up each local day for the calendar, by project and with the running ▶', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $bhn, 'project' => $bhnProject] = bagProject($admin, [$dev], 10 * 3600);
    ['task' => $cdos] = bagProject($admin, [$dev], 10 * 3600, 'cdos');
    myHoursEntry($dev, $bhn, 2, '09:00', '11:00');
    myHoursEntry($dev, $bhn, 1, '23:30', '23:50'); // 02:30 UTC of the next day: still yesterday here
    myHoursEntry($dev, $cdos, 1, '09:00', '10:00');
    $from = today('America/Argentina/Buenos_Aires')->subDays(2)->toDateString();
    $to = today('America/Argentina/Buenos_Aires')->toDateString();
    startTimer($dev, $bhn)->assertOk();
    $this->travel(30)->minutes();

    $all = $this->actingAs($dev)->getJson("/api/v1/time-entries/days?from={$from}&to={$to}")->assertOk();
    expect(array_column($all->json('data.days'), 'total_seconds'))->toBe([7200, 1200 + 3600, 1800])
        ->and($all->json('data.days.0.date'))->toBe($from)
        ->and($all->json('data.total_seconds'))->toBe(7200 + 4800 + 1800);

    $bhnOnly = $this->actingAs($dev)->getJson("/api/v1/time-entries/days?from={$from}&to={$to}&project_id={$bhnProject->id}")->assertOk();
    expect(array_column($bhnOnly->json('data.days'), 'total_seconds'))->toBe([7200, 1200, 1800]);
});

// CA-05.20g/h
it('shows someone else\'s calendar only to time.manage, for at most 62 days', function () {
    $admin = adminUser();
    [$dev, $mate] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $task] = bagProject($admin, [$dev, $mate], 10 * 3600);
    myHoursEntry($dev, $task);
    $from = today('America/Argentina/Buenos_Aires')->subDays(5)->toDateString();
    $to = today('America/Argentina/Buenos_Aires')->toDateString();

    $this->actingAs($mate)->getJson("/api/v1/time-entries/days?from={$from}&to={$to}&user_id={$dev->id}")->assertForbidden();
    $this->actingAs($admin)->getJson("/api/v1/time-entries/days?from={$from}&to={$to}&user_id={$dev->id}")
        ->assertOk()->assertJsonPath('data.total_seconds', 3600);
    $this->actingAs($dev)->getJson('/api/v1/time-entries/days?from=2026-01-01&to=2026-03-15')->assertUnprocessable()->assertJsonValidationErrors('to');
    $this->actingAs($dev)->getJson('/api/v1/time-entries/days?from=ayer&to=hoy')->assertUnprocessable();
});
