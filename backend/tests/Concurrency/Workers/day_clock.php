<?php

/**
 * Worker for the day clock under real concurrency (QA-15..18, RF-05.19).
 * Usage: php day_clock.php <action> <userId> [arg]
 *   day_start                 starts the person's day clock          → "<dayClockId>"
 *   timer_start <taskId>      presses ▶ on a task                    → "timer:<entryId>"
 *   split <dayClockId>        splits it with the JSON in env PARTS  → "split:<entries>"
 *   cut                       runs the minute job (timers:cut)      → "cut"
 * A business rule refusal prints "rule:<code>" and exits 0; anything else (deadlock, lock wait) exits 1.
 */

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\SplitDayClock;
use App\Domain\TimeTracking\Actions\StartDayClock;
use App\Domain\TimeTracking\Actions\StartTimer;
use App\Domain\TimeTracking\Models\DayClock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, "Refusing to run outside a *_testing database\n");
    exit(2);
}

$action = $argv[1];
$user = User::query()->findOrFail((int) $argv[2]);
$arg = $argv[3] ?? null;

try {
    echo match ($action) {
        'day_start' => $app->make(StartDayClock::class)($user)->id,
        // With a comment for the previous timer, so switching projects (two project locks) is exercised too.
        'timer_start' => 'timer:'.$app->make(StartTimer::class)($user, Task::query()->withoutGlobalScopes()->findOrFail((int) $arg), 'Cambio de tarea en paralelo')->id,
        'split' => 'split:'.count($app->make(SplitDayClock::class)(
            $user, DayClock::query()->withoutGlobalScopes()->findOrFail((int) $arg), json_decode((string) getenv('PARTS'), true),
        )['entries']),
        'cut' => Artisan::call('timers:cut') === 0 ? 'cut' : throw new RuntimeException('timers:cut failed'),
    };
} catch (BusinessRuleViolation $violation) {
    echo "rule:{$violation->ruleCode}";
}
