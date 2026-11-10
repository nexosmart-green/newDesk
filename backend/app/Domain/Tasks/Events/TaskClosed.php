<?php

namespace App\Domain\Tasks\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised inside the transaction that moves a task to a "closed" status.
 * TimeTracking listens to stop timers running on it (CA-04.03).
 */
final class TaskClosed
{
    use Dispatchable;

    public function __construct(public readonly Task $task, public readonly User $actor) {}
}
