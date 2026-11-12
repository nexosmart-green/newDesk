<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CreateAndStartTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\CreateAndStartTaskRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * RF-04.02 "Crear e iniciar".
 */
class CreateAndStartController extends Controller
{
    public function __invoke(CreateAndStartTaskRequest $request, Project $project, CreateAndStartTask $createAndStart): JsonResponse
    {
        $result = $createAndStart($request->user(), $project, $request->taskData(), $request->assigneeIds(), $request->previousComment());
        $task = $result['task']->load(Task::RESOURCE_RELATIONS);
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
