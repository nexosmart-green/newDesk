<?php

use App\Domain\Notifications\Notifications\HoursOverdrawnNotification;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/TimeTrackingHelpers.php';
require_once __DIR__.'/../Mcp/McpTestHelpers.php';

/** A part of the day: offsets in seconds from its start (RF-05.19). */
function dayPart(int $from, int $to, int $projectId, ?int $taskId, string $comment = 'Maquetado del checkout', ?string $newTask = null): array
{
    return ['from' => $from, 'to' => $to, 'project_id' => $projectId, 'task_id' => $taskId, 'new_task_title' => $newTask, 'comment' => $comment];
}

/** Starts a day, lets `$seconds` pass and stops it. */
function workedDay($user, int $seconds): DayClock
{
    test()->actingAs($user)->postJson('/api/v1/me/day-clock')->assertCreated();
    test()->travel($seconds)->seconds();
    test()->actingAs($user)->postJson('/api/v1/me/day-clock/stop')->assertOk()->assertJsonPath('data.status', 'pending');

    return DayClock::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

it('starts one day that blocks the ▶ and "Crear e iniciar", and a running ▶ blocks the day (CA-05.19a, CA-05.19b)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $mate = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev, $mate], 10 * 3600);

    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertCreated()->assertJsonPath('data.status', 'running');
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertUnprocessable()->assertJsonPath('data.code', 'day_clock_running');
    expect(DayClock::query()->count())->toBe(1);

    startTimer($dev, $task)->assertUnprocessable()->assertJsonPath('data.code', 'day_clock_running');
    $tasks = Task::query()->where('project_id', $project->id)->count();
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks/start", ['title' => 'Otra cosa'])
        ->assertUnprocessable()->assertJsonPath('data.code', 'day_clock_running');
    expect(Task::query()->where('project_id', $project->id)->count())->toBe($tasks);
    $this->actingAs($dev)->getJson('/api/v1/me/timer')->assertOk()->assertJsonPath('data.day_clock.status', 'running');

    startTimer($mate, $task)->assertOk();
    $this->actingAs($mate)->postJson('/api/v1/me/day-clock')->assertUnprocessable()->assertJsonPath('data.code', 'timer_running');
});

it('stays pending once stopped and blocks the ▶ and another day until split (CA-05.19c)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);

    workedDay($dev, 7200);

    $this->actingAs($dev)->getJson('/api/v1/me/timer')
        ->assertJsonPath('data.day_clock.status', 'pending')->assertJsonPath('data.day_clock.elapsed_seconds', 7200);
    startTimer($dev, $task)->assertUnprocessable()->assertJsonPath('data.code', 'day_split_pending');
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertUnprocessable()->assertJsonPath('data.code', 'day_split_pending');
});

it('splits the day into entries, leaves the gaps out and creates a new task assigned to me (CA-05.19d, CA-05.19e)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $playinn, 'task' => $checkout] = bagProject($admin, [$dev], 10 * 3600, 'pla');
    ['project' => $tol] = bagProject($admin, [$dev], 10 * 3600, 'tol');
    $day = workedDay($dev, 6 * 3600);

    $this->actingAs($dev)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => [
        dayPart(0, 3600, $playinn->id, $checkout->id),
        dayPart(3660, 7200, $tol->id, null, 'Reunión con el cliente y ranking', 'Reunión semanal'),
    ]])->assertOk()->assertJsonCount(2, 'data.entries')->assertJsonPath('data.overdrawn', []);

    $entries = TimeEntry::query()->where('user_id', $dev->id)->orderBy('started_at')->with('task')->get();
    expect($entries->pluck('seconds')->all())->toBe([3600, 3540])
        ->and($entries->every(fn (TimeEntry $entry) => $entry->source === EntrySource::DayClock && ! $entry->comment_pending))->toBeTrue()
        ->and($entries[0]->started_at->equalTo($day->started_at))->toBeTrue()
        ->and($entries[1]->task->code)->toBe('tol-2')
        ->and($entries[1]->task->isAssignedTo($dev))->toBeTrue()
        ->and($playinn->fresh()->consumed_seconds)->toBe(3600)
        ->and($tol->fresh()->consumed_seconds)->toBe(3540)
        ->and($day->fresh()->split_at)->not->toBeNull();

    $this->actingAs($dev)->getJson('/api/v1/me/timer')->assertJsonPath('data.day_clock', null);
    startTimer($dev, $checkout)->assertOk();
});

it('rejects parts outside the day, overlapping, without a real comment, on someone else\'s task or over my own hours (CA-05.19e)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $mate = userWithRole($admin);
    ['project' => $project, 'task' => $mine] = bagProject($admin, [$dev, $mate], 10 * 3600);
    ['project' => $other, 'task' => $otherTask] = bagProject($admin, [$dev], 10 * 3600, 'otr');
    $notMine = app(CreateTask::class)($admin, $project, ['title' => 'Del compañero'], [$mate->id]);
    $day = workedDay($dev, 3 * 3600);
    $split = fn (array $parts) => $this->actingAs($dev)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => $parts]);

    $split([dayPart(0, 3 * 3600 + 60, $project->id, $mine->id)])->assertUnprocessable()->assertJsonValidationErrors('parts.0.from');
    $split([dayPart(0, 3600, $project->id, $mine->id), dayPart(1800, 4000, $project->id, $mine->id)])->assertUnprocessable()->assertJsonValidationErrors('parts.1.from');
    $split([dayPart(0, 3600, $project->id, $mine->id, 'corto')])->assertUnprocessable()->assertJsonValidationErrors('parts.0.comment');
    $split([dayPart(0, 3600, $project->id, $notMine->id)])->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors) => str_contains($errors['parts.0.task_id'][0], 'asignadas a vos'));
    $split([dayPart(0, 3600, $project->id, $otherTask->id)])->assertUnprocessable()->assertJsonValidationErrors('parts.0.task_id');

    // A manual entry inside the day: the same minute is never billed twice.
    TimeEntry::query()->create(['company_id' => $dev->company_id, 'user_id' => $dev->id, 'task_id' => $mine->id, 'project_id' => $project->id,
        'started_at' => $day->started_at->copy()->addMinutes(30), 'ended_at' => $day->started_at->copy()->addMinutes(40), 'seconds' => 600,
        'source' => EntrySource::Manual, 'comment' => 'Carga manual previa', 'created_by' => $dev->id]);
    $split([dayPart(0, 3600, $project->id, $mine->id)])->assertUnprocessable()->assertJsonValidationErrors('parts.0.from');

    expect(TimeEntry::query()->where('source', EntrySource::DayClock)->count())->toBe(0)
        ->and($project->fresh()->consumed_seconds)->toBe(0)->and($other->fresh()->consumed_seconds)->toBe(0)
        ->and($day->fresh()->split_at)->toBeNull();

    // Only its owner splits a day.
    $this->actingAs($admin)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => []])->assertNotFound();
});

it('saves a part that overdraws the bag, tells Admin, spares the ▶ already running and then blocks new hours (CA-05.19f, CA-05.19g)', function () {
    Notification::fake();
    $admin = adminUser();
    $dev = userWithRole($admin);
    $mate = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev, $mate], 1800);

    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertCreated();
    $this->travel(2)->hours();
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock/stop')->assertOk();
    startTimer($mate, $task)->assertOk();
    $this->travel(10)->minutes();
    $day = DayClock::query()->where('user_id', $dev->id)->firstOrFail();

    // 1 h on a bag with 30 min loaded and 10 min running: 40 min over.
    $this->actingAs($dev)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => [dayPart(0, 3600, $project->id, $task->id)]])
        ->assertOk()->assertJsonPath('data.overdrawn.0.seconds', 2400)->assertJsonPath('data.overdrawn.0.project_id', $project->id);
    expect($project->fresh()->consumed_seconds)->toBe(3600);
    Notification::assertSentTo($admin, HoursOverdrawnNotification::class);

    // R-6: the budget cut spares the ▶ that was running; it keeps its whole time.
    $this->artisan('timers:cut')->assertSuccessful();
    expect(TimeEntry::query()->where('user_id', $mate->id)->running()->value('uncapped'))->toBeTrue();
    $this->travel(20)->minutes();
    stopTimer($mate)->assertOk();
    expect(TimeEntry::query()->where('user_id', $mate->id)->value('seconds'))->toBe(1800);

    // From now on nobody adds hours there, except another day split.
    startTimer($mate, $task)->assertUnprocessable()->assertJsonPath('data.code', 'no_hours_available');
    $this->actingAs($mate)->postJson("/api/v1/tasks/{$task->code}/time-entries", [
        'date' => now('America/Argentina/Buenos_Aires')->toDateString(), 'from' => '00:01', 'to' => '00:30', 'comment' => 'Carga manual tardía',
    ])->assertUnprocessable()->assertJsonPath('data.code', 'no_hours_available');
    $this->artisan('hours:reconcile', ['--dry-run' => true])->assertSuccessful();
});

it('ends a forgotten day at the person\'s local midnight, even if started at 23:50 (CA-05.19h)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $dev->forceFill(['timezone' => 'America/Bogota'])->save(); // UTC-5, the company is UTC-3

    $this->travelTo(Carbon::parse('2026-11-10 23:50:00', 'America/Bogota'));
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertCreated()
        ->assertJsonPath('data.cutoff_at', Carbon::parse('2026-11-11 00:00:00', 'America/Bogota')->utc()->toIso8601String());
    $this->travel(1)->hours();
    $this->artisan('timers:cut')->assertSuccessful();

    $day = DayClock::query()->where('user_id', $dev->id)->sole();
    expect($day->ended_at->equalTo(Carbon::parse('2026-11-11 00:00:00', 'America/Bogota')))->toBeTrue()
        ->and($day->elapsedSeconds())->toBe(600);

    // Stopping late (no job ran) never stretches a day past its midnight either.
    $day->forceFill(['split_at' => now()])->save();
    $this->travelTo(Carbon::parse('2026-11-12 18:00:00', 'America/Bogota'));
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock')->assertCreated();
    $this->travelTo(Carbon::parse('2026-11-13 09:00:00', 'America/Bogota'));
    $this->actingAs($dev)->postJson('/api/v1/me/day-clock/stop')->assertOk()->assertJsonPath('data.elapsed_seconds', 6 * 3600);
});

it('closes a day counting nothing, refuses a second split and accepts a late one (CA-05.19i)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    $day = workedDay($dev, 3600);
    $this->travel(8)->days();

    $this->actingAs($dev)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => []])->assertOk();
    $this->actingAs($dev)->postJson("/api/v1/me/day-clocks/{$day->id}/split", ['parts' => [dayPart(0, 600, $project->id, $task->id)]])
        ->assertUnprocessable()->assertJsonPath('data.code', 'day_already_split');
    expect(TimeEntry::query()->count())->toBe(0)->and($project->fresh()->consumed_seconds)->toBe(0);
    startTimer($dev, $task)->assertOk();
});

it('shows the day in whoami and blocks start_timer through MCP (RF-05.19, RF-12)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    workedDay($dev, 600);
    $token = mcpToken($dev);

    $whoami = mcpResult(mcpCall($token, 'whoami')->assertOk());
    expect($whoami['json']['day_clock']['status'])->toBe('pending');
    $start = mcpResult(mcpCall($token, 'start_timer', ['code' => $task->code])->assertOk());
    expect($start['error'])->toBeTrue()->and($start['text'])->toContain('día sin repartir');
});
