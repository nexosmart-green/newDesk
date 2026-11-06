<?php

namespace App\Domain\Tasks\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * New assignees on a task. Notifications (RF-09.01) listen to this.
 */
final class TaskAssigned
{
    use Dispatchable;

    /** @param  list<User>  $assignees  only the newly added ones */
    public function __construct(public readonly Task $task, public readonly array $assignees, public readonly User $actor) {}
}
