<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Tasks\Actions\CommentOnTask;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\CommentOnTaskRequest;
use App\Http\Resources\TaskCommentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $comments = $task->comments()->with(['author', 'mentions'])->oldest('id')->paginate(100);

        return TaskCommentResource::collection($comments);
    }

    public function store(CommentOnTaskRequest $request, Task $task, CommentOnTask $commentOnTask): JsonResponse
    {
        $comment = $commentOnTask($request->user(), $task, $request->body(), $request->mentionedUserIds());

        return ApiResponse::ok(TaskCommentResource::make($comment), 'Comentario publicado.', 201);
    }
}
