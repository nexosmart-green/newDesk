<?php

use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\TimeTracking\Models\TimeEntry;

require_once __DIR__.'/TimeTrackingHelpers.php';

it('QA-01: does not start a timer without hours and says why (CA-05.05)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 0);

    startTimer($dev, $task)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sin horas disponibles en BHN: pedí una ampliación.')
        ->assertJsonPath('data.code', 'no_hours_available');
    expect(TimeEntry::query()->count())->toBe(0);
});

it('QA-03: a double click or a second device never creates two timers (R-2, CA-05.03)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    $first = startTimer($dev, $task)->assertOk()->json('data.id');
    $second = startTimer($dev, $task)->assertOk()->json('data.id');
    $this->travel(5)->minutes();

    expect($second)->toBe($first)->and(TimeEntry::query()->count())->toBe(1);
    $this->actingAs($dev)->getJson('/api/v1/me/timer')
        ->assertJsonPath('data.active.id', $first)
        ->assertJsonPath('data.active.elapsed_seconds', 300);
});

it('requires a comment of at least 10 chars to stop, and keeps running without it (RF-05.16, CA-12.x)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    startTimer($dev, $task);

    stopTimer($dev, null)->assertUnprocessable()->assertJsonPath('data.code', 'comment_required');
    stopTimer($dev, 'corto')->assertUnprocessable();
    expect(TimeEntry::query()->running()->count())->toBe(1);

    $this->travel(30)->minutes();
    stopTimer($dev, 'Ajustes de layout')->assertOk()->assertJsonPath('data.elapsed_seconds', 1800)->assertJsonPath('data.comment', 'Ajustes de layout');
    expect(ActivityLog::query()->where('event', 'time.recorded')->exists())->toBeTrue();
});

it('switching tasks asks for the previous comment and closes it in the same step (RF-05.02)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $first] = bagProject($admin, [$dev], 3600, 'one');
    ['task' => $second] = bagProject($admin, [$dev], 3600, 'two');
    startTimer($dev, $first);
    $this->travel(10)->minutes();

    startTimer($dev, $second)->assertUnprocessable()->assertJsonPath('data.code', 'previous_comment_required');
    startTimer($dev, $second, 'Terminé el formulario')->assertOk()->assertJsonPath('data.task.code', 'two-1');

    $closed = TimeEntry::query()->where('task_id', $first->id)->firstOrFail();
    expect($closed->seconds)->toBe(600)->and($closed->stop_reason->value)->toBe('switch')->and($closed->comment)->toBe('Terminé el formulario');
});

it('only lets people track tasks assigned to them', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $other = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 3600);
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $other->id]);

    startTimer($other, $task)->assertUnprocessable()->assertJsonPath('data.code', 'task_not_assigned');
});

it('QA-07: "crear e iniciar" asks for the previous comment before creating anything (CA-04.02)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 3600);
    startTimer($dev, $task);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks/start", ['title' => 'Bug urgente'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'previous_comment_required');
    expect($project->fresh()->task_sequence)->toBe(1);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks/start", ['title' => 'Bug urgente', 'previous_comment' => 'Dejo el header a medias'])
        ->assertCreated()
        ->assertJsonPath('data.task.code', 'bhn-2')
        ->assertJsonPath('data.time_entry.task.code', 'bhn-2')
        ->assertJsonPath('data.timer_error', null);
    expect(TimeEntry::query()->running()->count())->toBe(1);
});

it('"crear e iniciar" without hours creates the task but not the timer (CA-04.02b)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 0);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks/start", ['title' => 'Algo'])
        ->assertCreated()
        ->assertJsonPath('data.task.code', 'bhn-2')
        ->assertJsonPath('data.time_entry', null)
        ->assertJsonPath('data.timer_error.code', 'no_hours_available');
});

it('internal projects never block but still record hours (RF-05.14)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 0, 'nxs', 'internal');

    startTimer($dev, $task)->assertOk();
    $this->travel(2)->hours();
    stopTimer($dev)->assertOk();

    expect($project->fresh()->consumed_seconds)->toBe(7200);
    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/hours")
        ->assertJsonPath('data.has_cap', false)->assertJsonPath('data.available_seconds', null);
});

it('another company cannot see nor start timers on our tasks (404)', function () {
    $admin = adminUser('a@nexo.com');
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    startTimer(adminUser('b@other.com'), $task)->assertNotFound();
});
