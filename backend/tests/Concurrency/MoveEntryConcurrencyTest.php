<?php

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\LogManualTime;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/../Feature/TimeTracking/TimeTrackingHelpers.php';

afterEach(fn () => $this->truncateTablesForAllConnections());

/**
 * Runs every command at the same time: `move <actor> <entry> <task>` goes to the move worker, the rest
 * (`timer_start`, `cut`) to the day clock worker. Database cache so the per-user lock is shared like Redis.
 *
 * @param  list<list<string>>  $commands
 * @return list<string> each process' output, in command order
 */
function movePool(array $commands): array
{
    $results = Process::pool(function ($pool) use ($commands) {
        foreach ($commands as $command) {
            $pool->env(['APP_ENV' => 'testing', 'DB_DATABASE' => 'newdesk_testing', 'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'])
                ->timeout(120)
                ->command($command[0] === 'move'
                    ? [PHP_BINARY, base_path('tests/Concurrency/Workers/move_entry.php'), ...array_slice($command, 1)]
                    : [PHP_BINARY, base_path('tests/Concurrency/Workers/day_clock.php'), ...$command]);
        }
    })->start()->wait();

    return $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput());

        return trim($result->output());
    })->values()->all();
}

/** An hour of manual time yesterday, `$slot` hours after 09:00 local. */
function hourYesterday(User $user, Task $task, int $slot): TimeEntry
{
    $start = today('America/Argentina/Buenos_Aires')->subDay()->setTime(9 + $slot, 0)->utc();

    return app(LogManualTime::class)($user, $task, $start, $start->copy()->addHour(), 'Trabajo del cliente');
}

// QA-19 / R-7, CA-05.20d
it('moves entries A→B and B→A at once while ▶ start and the minute cut runs, without deadlocks', function () {
    $admin = adminUser();
    [$one, $two, $x, $y] = [userWithRole($admin), userWithRole($admin), userWithRole($admin), userWithRole($admin)];
    ['project' => $a, 'task' => $taskA] = bagProject($admin, [$one, $two, $x, $y], 10 * 3600, 'uno');
    ['project' => $b, 'task' => $taskB] = bagProject($admin, [$one, $two, $x, $y], 10 * 3600, 'dos');
    $fromA = array_map(fn (int $slot) => hourYesterday($one, $taskA, $slot), [0, 1, 2]);
    $fromB = array_map(fn (int $slot) => hourYesterday($two, $taskB, $slot), [0, 1, 2]);

    $outputs = movePool([
        ...array_map(fn (TimeEntry $entry) => ['move', (string) $one->id, (string) $entry->id, (string) $taskB->id], $fromA),
        ...array_map(fn (TimeEntry $entry) => ['move', (string) $two->id, (string) $entry->id, (string) $taskA->id], $fromB),
        ['timer_start', (string) $x->id, (string) $taskA->id],
        ['timer_start', (string) $y->id, (string) $taskB->id],
        ['cut', (string) $admin->id],
        ['cut', (string) $admin->id],
    ]);

    foreach (array_slice($outputs, 0, 6) as $output) {
        expect($output)->toStartWith('moved:');
    }
    expect(TimeEntry::query()->withoutGlobalScopes()->where('user_id', $one->id)->pluck('project_id')->unique()->all())->toBe([$b->id])
        ->and(TimeEntry::query()->withoutGlobalScopes()->where('user_id', $two->id)->pluck('project_id')->unique()->all())->toBe([$a->id])
        ->and($a->fresh()->consumed_seconds)->toBe(3 * 3600)
        ->and($b->fresh()->consumed_seconds)->toBe(3 * 3600);
    $this->artisan('hours:reconcile', ['--dry-run' => true])->assertSuccessful();
});

// QA-19 / R-7, CA-05.20e
it('lets exactly one of two moves into a bag with room for one', function () {
    $admin = adminUser();
    [$one, $two] = [userWithRole($admin), userWithRole($admin)];
    ['task' => $taskA] = bagProject($admin, [$one, $two], 10 * 3600, 'uno');
    ['project' => $small, 'task' => $taskSmall] = bagProject($admin, [$one, $two], 3600, 'chico');
    $first = hourYesterday($one, $taskA, 0);
    $second = hourYesterday($two, $taskA, 0);

    $outputs = movePool([
        ['move', (string) $one->id, (string) $first->id, (string) $taskSmall->id],
        ['move', (string) $two->id, (string) $second->id, (string) $taskSmall->id],
    ]);

    expect(array_map(fn (string $output) => str_starts_with($output, 'moved:') ? 'moved' : $output, $outputs))
        ->toEqualCanonicalizing(['moved', 'rule:manual_exceeds_bag'])
        ->and($small->fresh()->consumed_seconds)->toBe(3600);
    $this->artisan('hours:reconcile', ['--dry-run' => true])->assertSuccessful();
});
