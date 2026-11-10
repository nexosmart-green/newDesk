<?php

/**
 * Worker process for concurrency tests: creates N tasks through the real CreateTask action.
 * Usage: php create_tasks.php <actorId> <projectId> <count>
 */

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, "Refusing to run outside a *_testing database\n");
    exit(2);
}

[, $actorId, $projectId, $count] = $argv;
$actor = User::query()->findOrFail((int) $actorId);
$createTask = $app->make(CreateTask::class);

for ($i = 1; $i <= (int) $count; $i++) {
    $project = Project::query()->withoutGlobalScopes()->findOrFail((int) $projectId);
    $createTask($actor, $project, ['title' => "Tarea concurrente {$i} (pid ".getmypid().')']);
}
