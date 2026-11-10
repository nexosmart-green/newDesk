<?php

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/../Feature/TimeTracking/TimeTrackingHelpers.php';

afterEach(fn () => $this->truncateTablesForAllConnections());

/**
 * Runs the day clock worker once per command, all at the same time. The cache is the database so the per-user
 * lock is shared like Redis in prod; queue sync + array mailer keep the notifications inside the test.
 *
 * @param  list<list<string>>  $commands  [action, userId, arg?]
 * @return list<string> each process' output, in command order
 */
function dayClockPool(array $commands, array $env = []): array
{
    $results = Process::pool(function ($pool) use ($commands, $env) {
        foreach ($commands as $command) {
            $pool->env(['APP_ENV' => 'testing', 'DB_DATABASE' => 'newdesk_testing', 'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', ...$env])
                ->timeout(120)
                ->command($command[0] === 'create_tasks'
                    ? [PHP_BINARY, base_path('tests/Concurrency/Workers/create_tasks.php'), ...array_slice($command, 1)]
                    : [PHP_BINARY, base_path('tests/Concurrency/Workers/day_clock.php'), ...$command]);
        }
    })->start()->wait();

    return $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput());

        return trim($result->output());
    })->values()->all();
}

/** A stopped day of `$seconds`, waiting to be split. */
function pendingDay($user, int $seconds): DayClock
{
    return DayClock::query()->create(['company_id' => $user->company_id, 'user_id' => $user->id,
        'started_at' => now()->subSeconds($seconds), 'ended_at' => now()]);
}

function reconcileIsClean(): void
{
    test()->artisan('hours:reconcile', ['--dry-run' => true])->assertSuccessful();
}

// QA-15 / CA-05.19a-b
it('leaves a single thing running when the day and the ▶ are pressed at once, and one day for six starts', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 10 * 3600);

    $outputs = dayClockPool([
        ...array_fill(0, 4, ['day_start', (string) $dev->id]),
        ...array_fill(0, 4, ['timer_start', (string) $dev->id, (string) $task->id]),
    ]);
    $running = DayClock::query()->withoutGlobalScopes()->whereNull('ended_at')->count()
        + TimeEntry::query()->withoutGlobalScopes()->running()->count();
    expect($running)->toBe(1);
    foreach ($outputs as $output) {
        expect($output)->toMatch('/^(\d+|timer:\d+|rule:(timer_running|day_clock_running))$/');
    }

    $this->truncateTablesForAllConnections();
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outputs = dayClockPool(array_fill(0, 6, ['day_start', (string) $dev->id]));
    $day = DayClock::query()->withoutGlobalScopes()->sole();
    foreach ($outputs as $output) {
        expect($output)->toBeIn([(string) $day->id, 'rule:day_clock_running']);
    }
});

// QA-16 / CA-05.19i
it('records a day once when six identical splits race, and the cache stays equal to the ledger', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 10 * 3600);
    $day = pendingDay($dev, 7200);
    $parts = json_encode([
        ['from' => 0, 'to' => 3600, 'project_id' => $project->id, 'task_id' => $task->id, 'new_task_title' => null, 'comment' => 'Primera parte del día'],
        ['from' => 4000, 'to' => 7200, 'project_id' => $project->id, 'task_id' => $task->id, 'new_task_title' => null, 'comment' => 'Segunda parte del día'],
    ]);

    $outputs = dayClockPool(array_fill(0, 6, ['split', (string) $dev->id, (string) $day->id]), ['PARTS' => $parts]);

    expect(array_count_values($outputs))->toEqualCanonicalizing(['split:2' => 1, 'rule:day_already_split' => 5])
        ->and(TimeEntry::query()->withoutGlobalScopes()->where('source', EntrySource::DayClock)->count())->toBe(2)
        ->and($project->fresh()->consumed_seconds)->toBe(3600 + 3200);
    reconcileIsClean();
});

// QA-17 / CA-05.19f-g, R-6
it('keeps the ▶ that were running whole when a split overdraws, while cuts and new ▶ race it', function () {
    $admin = adminUser();
    [$dev, $a, $b, $c] = [userWithRole($admin), userWithRole($admin), userWithRole($admin), userWithRole($admin)];
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev, $a, $b, $c], 3600);
    $running = TimeEntry::query()->create(['company_id' => $a->company_id, 'user_id' => $a->id, 'task_id' => $task->id, 'project_id' => $project->id,
        'started_at' => now()->subMinutes(40), 'source' => EntrySource::Timer, 'created_by' => $a->id]);
    $day = pendingDay($dev, 7200);
    $parts = json_encode([['from' => 0, 'to' => 7200, 'project_id' => $project->id, 'task_id' => $task->id, 'new_task_title' => null, 'comment' => 'Todo el día en BH News']]);

    $outputs = dayClockPool([
        ['split', (string) $dev->id, (string) $day->id],
        ...array_fill(0, 3, ['cut', (string) $admin->id]),
        ['timer_start', (string) $b->id, (string) $task->id],
        ['timer_start', (string) $c->id, (string) $task->id],
    ], ['PARTS' => $parts]);
    $this->artisan('timers:cut')->assertSuccessful();

    expect($outputs[0])->toBe('split:1')
        ->and($running->fresh()->ended_at)->toBeNull()
        ->and($running->fresh()->uncapped)->toBeTrue();
    // A ▶ that won the race before the split keeps running uncapped; one that came after was refused. Never a capped one left.
    expect(TimeEntry::query()->withoutGlobalScopes()->where('project_id', $project->id)->running()->where('uncapped', false)->count())->toBe(0);
    foreach (array_slice($outputs, 4) as $output) {
        expect($output)->toMatch('/^(timer:\d+|rule:no_hours_available)$/');
    }
    reconcileIsClean();
});

// QA-18 / R-6, CA-04.01
it('splits with a new task while ▶ switch projects and tasks are created, without deadlocks or gaps in the codes', function () {
    $admin = adminUser();
    [$dev, $y, $z] = [userWithRole($admin), userWithRole($admin), userWithRole($admin)];
    ['project' => $p1, 'task' => $t1] = bagProject($admin, [$dev, $y, $z], 10 * 3600, 'uno');
    ['project' => $p2, 'task' => $t2] = bagProject($admin, [$dev, $y, $z], 10 * 3600, 'dos');
    expect($p1->id)->toBeLessThan($p2->id);
    $day = pendingDay($dev, 3600);
    $parts = json_encode([
        ['from' => 0, 'to' => 1800, 'project_id' => $p1->id, 'task_id' => $t1->id, 'new_task_title' => null, 'comment' => 'Parte del proyecto uno'],
        ['from' => 1800, 'to' => 3600, 'project_id' => $p2->id, 'task_id' => null, 'new_task_title' => 'Tarea nueva del reparto', 'comment' => 'Parte del proyecto dos'],
    ]);

    $outputs = dayClockPool([
        ['split', (string) $dev->id, (string) $day->id],
        ['timer_start', (string) $y->id, (string) $t2->id],
        ['timer_start', (string) $y->id, (string) $t1->id],
        ['timer_start', (string) $z->id, (string) $t1->id],
        ['timer_start', (string) $z->id, (string) $t2->id],
        ['create_tasks', (string) $admin->id, (string) $p2->id, '5'],
        ['create_tasks', (string) $admin->id, (string) $p2->id, '5'],
    ], ['PARTS' => $parts]);

    expect($outputs[0])->toBe('split:2');
    $numbers = Task::query()->withoutGlobalScopes()->where('project_id', $p2->id)->orderBy('number')->pluck('number')->all();
    expect($numbers)->toBe(range(1, 12))
        ->and(Task::query()->withoutGlobalScopes()->where('project_id', $p2->id)->distinct()->count('code'))->toBe(12);
    reconcileIsClean();
});
