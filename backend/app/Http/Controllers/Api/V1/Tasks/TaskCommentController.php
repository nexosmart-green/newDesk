<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\CommentOnTask;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskCommentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $comments = $task->comments()->with(['author', 'mentions'])->oldest('id')->paginate(100);

        return TaskCommentResource::collection($comments)->additional(['message' => null, 'errors' => null]);
    }

    public function store(Request $request, Task $task, CommentOnTask $commentOnTask): JsonResponse
    {
        Gate::authorize('comment', $task);
        $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'mentioned_user_ids' => ['sometimes', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer', 'distinct'],
        ]);

        $comment = $commentOnTask(
            $request->user(),
            $task,
            $request->string('body')->trim()->value(),
            array_map('intval', $request->input('mentioned_user_ids', [])),
        );

        return ApiResponse::ok(TaskCommentResource::make($comment), 'Comentario publicado.', 201);
    }
}
