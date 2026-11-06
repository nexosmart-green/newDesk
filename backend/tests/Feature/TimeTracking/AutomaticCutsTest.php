<?php

use App\Domain\Tasks\Models\TaskStatus;
use App\Domain\TimeTracking\Events\HourThresholdReached;
use App\Domain\TimeTracking\Events\TimerAutoStopped;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/TimeTrackingHelpers.php';

it('QA-02: two timers use up the bag and are cut so Consumidas = Bolsa exactly (CA-05.06, R-1)', function () {
    Event::fake([TimerAutoStopped::class]);
    $admin = adminUser();
    $ana = userWithRole($admin);
    $beto = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$ana, $beto], 1800);

    startTimer($ana, $task)->assertOk();
    startTimer($beto, $task)->assertOk();

    $this->travel(14)->minutes();
    $this->artisan('timers:cut')->assertSuccessful();
    expect(TimeEntry::query()->running()->count())->toBe(2);

    // The job runs late (16 min > 15): the overflow is trimmed from the last stretch.
    $this->travel(2)->minutes();
    $this->artisan('timers:cut')->assertSuccessful();

    $project->refresh();
    expect(TimeEntry::query()->running()->count())->toBe(0)
        ->and($project->consumed_seconds)->toBe($project->allocated_seconds)
        ->and((int) TimeEntry::query()->sum('seconds'))->toBe(1800)
        ->and(TimeEntry::query()->where('comment_pending', true)->count())->toBe(2);
    Event::assertDispatchedTimes(TimerAutoStopped::class, 2);

    startTimer($ana, $task)->assertUnprocessable()->assertJsonPath('data.code', 'comment_pending');
});

it('QA-04: an extension lifts the block, is logged, and does not resume cut timers (CA-05.04, R-3)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 0);
    startTimer($dev, $task)->assertUnprocessable();

    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/allocations", ['seconds' => 5 * 3600, 'reason' => 'Presupuesto #123'])
        ->assertCreated()->assertJsonPath('data.available_seconds', 18000)->assertJsonPath('data.is_exhausted', false);

    expect(TimeEntry::query()->count())->toBe(0);
    startTimer($dev, $task)->assertOk();

    $activity = $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/activity")->json('data');
    expect(collect($activity)->firstWhere('event', 'hours.allocated')['after']['reason'])->toBe('Presupuesto #123');
});

it('only hours.allocate loads hours and the reason is mandatory (RF-05.04)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/allocations", ['seconds' => 3600, 'reason' => 'Yo me cargo'])
        ->assertForbidden()->assertJsonPath('data.code', 'cannot_allocate');
    $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/allocations", ['seconds' => 3600])->assertUnprocessable();
});

it('QA-05: a forgotten timer is cut at the 10 h deadline and asks for review (CA-05.10, P-07)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 50 * 3600);
    startTimer($dev, $task);

    $this->travel(11)->hours();
    $this->artisan('timers:cut')->assertSuccessful();

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->seconds)->toBe(36000)->and($entry->stop_reason->value)->toBe('forgotten')->and($entry->comment_pending)->toBeTrue();

    $this->actingAs($dev)->getJson('/api/v1/me/timer')->assertJsonPath('data.active', null)->assertJsonCount(1, 'data.pending_comments');
    $this->actingAs($dev)->putJson("/api/v1/time-entries/{$entry->id}/comment", ['comment' => 'Diseño de la home'])->assertOk();
    startTimer($dev, $task)->assertOk();
});

it('QA-08: closing the task, finishing the project or deactivating the person cuts the timer (CA-04.03, CA-03.08, CA-01.10)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 10 * 3600);
    $closed = TaskStatus::query()->forCompany($admin->company_id)->where('kind', 'closed')->firstOrFail();
    $open = TaskStatus::query()->forCompany($admin->company_id)->where('name', 'En curso')->firstOrFail();
    $complete = fn () => TimeEntry::query()->where('comment_pending', true)->get()->each(fn ($e) => $e->forceFill(['comment_pending' => false, 'comment' => 'Revisado ok'])->save());

    startTimer($dev, $task)->assertOk();
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->code}/status", ['status_id' => $closed->id])->assertOk();
    expect(TimeEntry::query()->latest('id')->first()->stop_reason->value)->toBe('task_closed');

    $complete();
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->code}/status", ['status_id' => $open->id]);
    startTimer($dev, $task)->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/people/{$dev->id}/deactivate")->assertOk();
    expect(TimeEntry::query()->latest('id')->first()->stop_reason->value)->toBe('user_deactivated');

    $this->actingAs($admin)->postJson("/api/v1/people/{$dev->id}/reactivate");
    $complete();
    startTimer($dev->fresh(), $task)->assertOk();
    $this->actingAs($admin)->putJson("/api/v1/projects/{$project->id}/status", ['status' => 'finished'])->assertOk();
    expect(TimeEntry::query()->latest('id')->first()->stop_reason->value)->toBe('project_closed')
        ->and(TimeEntry::query()->running()->count())->toBe(0);
});

it('alerts once when crossing 80% and 100% (RF-05.07)', function () {
    Event::fake([HourThresholdReached::class]);
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    startTimer($dev, $task);
    $this->travel(50)->minutes();
    stopTimer($dev)->assertOk();
    startTimer($dev, $task);
    $this->travel(1)->minutes();
    stopTimer($dev)->assertOk();

    Event::assertDispatchedTimes(HourThresholdReached::class, 1);
    Event::assertDispatched(HourThresholdReached::class, fn ($event) => $event->percent === 80);
});

it('reconciles caches with the ledgers', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);

    $this->artisan('hours:reconcile')->assertSuccessful();
    $project->forceFill(['consumed_seconds' => 999])->saveQuietly();
    $this->artisan('hours:reconcile')->assertFailed();
    expect($project->fresh()->consumed_seconds)->toBe(0);
});
