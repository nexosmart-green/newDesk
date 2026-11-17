<?php

/**
 * Worker for QA-19 (R-7): corrects a time entry onto another task, possibly of another project.
 * Usage: php move_entry.php <actorId> <entryId> <taskId>  → "moved:<entryId>"
 * A business rule refusal prints "rule:<code>" and exits 0; anything else (deadlock, lock wait) exits 1.
 */

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CorrectTimeEntry;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, "Refusing to run outside a *_testing database\n");
    exit(2);
}

[, $actorId, $entryId, $taskId] = $argv;
$actor = User::query()->findOrFail((int) $actorId);
$entry = TimeEntry::query()->withoutGlobalScopes()->findOrFail((int) $entryId);
$task = Task::query()->withoutGlobalScopes()->findOrFail((int) $taskId);

try {
    echo 'moved:'.$app->make(CorrectTimeEntry::class)($actor, $entry, null, null, 'Lo paso al proyecto correcto', $task)->id;
} catch (BusinessRuleViolation $violation) {
    echo "rule:{$violation->ruleCode}";
}
