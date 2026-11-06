<?php

namespace App\Domain\Tasks\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskComment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Notifications (RF-09.02) tell the mentioned people.
 */
final class CommentPosted
{
    use Dispatchable;

    /** @param  list<User>  $mentioned */
    public function __construct(public readonly TaskComment $comment, public readonly array $mentioned) {}
}
