<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A checklist line is edited by whoever may edit its task (404 for outsiders, CA-03.03).
 */
class TaskChecklistItemPolicy
{
    public function update(User $user, TaskChecklistItem $item): Response
    {
        $task = $item->task;

        return $task !== null ? Gate::forUser($user)->inspect('update', $task) : Response::denyAsNotFound();
    }

    public function delete(User $user, TaskChecklistItem $item): Response
    {
        return $this->update($user, $item);
    }
}
