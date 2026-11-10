<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CreateAndStartTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * RF-04.02 "Crear e iniciar".
 */
class CreateAndStartController extends Controller
{
    public function __invoke(SaveTaskRequest $request, Project $project, CreateAndStartTask $createAndStart): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);
        $request->validate(['previous_comment' => ['nullable', 'string', 'max:5000']]);

        $result = $createAndStart($request->user(), $project, $request->taskData(), $request->assigneeIds(), $request->input('previous_comment'));
        $task = $result['task']->load(['status', 'assignees', 'project']);
        $timerError = $result['timer_error'];

        return ApiResponse::ok([
            'task' => TaskResource::make($task),
            'time_entry' => $result['entry'] ? TimeEntryResource::make($result['entry']) : null,
            'timer_error' => $timerError ? ['code' => $timerError->ruleCode, 'message' => $timerError->getMessage(), ...$timerError->context] : null,
        ], $timerError
            ? "Tarea {$task->code} creada, pero el cronómetro no arrancó: {$timerError->getMessage()}"
            : "Tarea {$task->code} creada y cronómetro iniciado.", 201);
    }
}
