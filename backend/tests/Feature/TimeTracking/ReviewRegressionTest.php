<?php

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\AutoStopTimers;
use App\Domain\TimeTracking\Actions\CloseTimeEntry;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;

require_once __DIR__.'/TimeTrackingHelpers.php';

it('never closes an entry twice: stop after an automatic cut keeps the cache equal to the entries (invariant 3)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 10 * 3600);
    startTimer($dev, $task);
    $this->travel(20)->minutes();

    $entry = TimeEntry::query()->firstOrFail();
    app(AutoStopTimers::class)->forTask($task);                      // the cut wins the race
    $closed = app(CloseTimeEntry::class)($entry, Project::query()->find($project->id), now()->addMinutes(5), StopReason::User, 'Tarde, ya cortado');

    expect($closed->stop_reason->value)->toBe('task_closed')->and($closed->comment_pending)->toBeTrue();
    stopTimer($dev)->assertNotFound()->assertJsonPath('data.code', 'no_active_timer');
    expect($project->fresh()->consumed_seconds)->toBe((int) TimeEntry::query()->sum('seconds'));
});

it('does not cut timers when an extension landed before the cut took the lock (R-3)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 600);
    startTimer($dev, $task);
    $this->travel(11)->minutes();
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/allocations", ['seconds' => 3600, 'reason' => 'Ampliación'])->assertCreated();

    $this->artisan('timers:cut');

    expect(TimeEntry::query()->running()->count())->toBe(1);
});

it('a person cannot move an old entry into the window by correcting it (RF-05.15)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $admin], 50 * 3600);
    $old = today('America/Argentina/Buenos_Aires')->subDays(30)->toDateString();
    $id = $this->actingAs($admin)->postJson("/api/v1/tasks/{$task->code}/time-entries", ['date' => $old, 'from' => '09:00', 'to' => '10:00', 'comment' => 'Carga vieja por admin'])->json('data.id');
    TimeEntry::query()->whereKey($id)->update(['user_id' => $dev->id]);

    $this->actingAs($dev)->putJson("/api/v1/time-entries/{$id}", [
        'date' => today('America/Argentina/Buenos_Aires')->subDay()->toDateString(), 'from' => '09:00', 'to' => '12:00', 'comment' => 'Muevo esto a ayer',
    ])->assertUnprocessable()->assertJsonPath('data.code', 'outside_manual_window');
});

it('only time.manage deletes entries; people cannot erase their auto-cut pending ones (RF-05.15/17)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    startTimer($dev, $task);
    $this->travel(30)->minutes();
    app(AutoStopTimers::class)->forTask($task);
    $id = TimeEntry::query()->value('id');

    $this->actingAs($dev)->deleteJson("/api/v1/time-entries/{$id}")->assertForbidden();
    $this->actingAs($admin)->deleteJson("/api/v1/time-entries/{$id}")->assertOk();
});
