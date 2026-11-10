<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Events\CommentPosted;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskComment;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.06: comment with @mentions. Mentions are explicit user ids (the UI/MCP resolves "@name" to an id),
 * restricted to project members so nobody outside learns the task exists.
 */
final class CommentOnTask
{
    public function __construct(private readonly ResolveProjectMembers $resolveMembers) {}

    /** @param  list<int>  $mentionedIds */
    public function __invoke(User $author, Task $task, string $body, array $mentionedIds = []): TaskComment
    {
        $mentioned = array_values(array_filter(
            ($this->resolveMembers)($task->project, $mentionedIds),
            fn (User $user) => ! $user->is($author),
        ));

        $comment = DB::transaction(function () use ($author, $task, $body, $mentioned) {
            $comment = TaskComment::query()->create(['task_id' => $task->id, 'user_id' => $author->id, 'body' => $body]);
            $comment->mentions()->attach(array_map(fn (User $user) => $user->id, $mentioned));

            return $comment;
        });

        CommentPosted::dispatch($comment, $mentioned);

        return $comment->load(['author', 'mentions']);
    }
}
