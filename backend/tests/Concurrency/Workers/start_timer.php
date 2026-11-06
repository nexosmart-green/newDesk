<?php

/**
 * Worker for QA-03 under real concurrency: presses ▶ on the same task, as several devices at once.
 * Usage: php start_timer.php <userId> <taskId>
 */

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\StartTimer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, "Refusing to run outside a *_testing database\n");
    exit(2);
}

[, $userId, $taskId] = $argv;
$user = User::query()->findOrFail((int) $userId);
$task = Task::query()->withoutGlobalScopes()->findOrFail((int) $taskId);

echo $app->make(StartTimer::class)($user, $task)->id;
