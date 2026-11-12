<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityFeed;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Actions\MoveTask;
use App\Domain\Tasks\Actions\UpdateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\ChangeTaskStatusRequest;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Http\Requests\Tasks\SetTaskAssigneesRequest;
use App\Http\Requests\Tasks\TaskListRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /** Project "Tareas" tab: list and board over the same data (RF-04.04). */
    public function index(TaskListRequest $request, Project $project, TaskList $taskList): AnonymousResourceCollection
    {
        return TaskResource::collection($taskList($request->user(), $project, $request->filters(), $request->perPage()));
    }

    /** "Mis tareas" cross-project (RF-04.07). */
    public function mine(TaskListRequest $request, TaskList $taskList): AnonymousResourceCollection
    {
        $filters = [...$request->filters(), 'mine' => true];

        return TaskResource::collection($taskList($request->user(), null, $filters, $request->perPage()));
    }

    /** "Todas las tareas": every task of the projects the viewer can see. */
    public function all(TaskListRequest $request, TaskList $taskList): AnonymousResourceCollection
    {
        return TaskResource::collection($taskList($request->user(), null, $request->filters(), $request->perPage()));
    }

    public function store(SaveTaskRequest $request, Project $project, CreateTask $createTask): JsonResponse
    {
        $task = $createTask($request->user(), $project, $request->taskData(), $request->assigneeIds());

        return ApiResponse::ok(TaskResource::make($task->load(Task::RESOURCE_RELATIONS)), "Tarea {$task->code} creada.", 201);
    }

    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $task->load([...Task::RESOURCE_RELATIONS, 'creator', 'checklistItems.doneBy'])->loadCount('attachments');

        return ApiResponse::ok(TaskResource::make($task));
    }

    public function update(SaveTaskRequest $request, Task $task, UpdateTask $updateTask): JsonResponse
    {
        $task = $updateTask($request->user(), $task, $request->taskData());

        return ApiResponse::ok(TaskResource::make($task->load(Task::RESOURCE_RELATIONS)), 'Tarea actualizada.');
    }

    public function updateStatus(ChangeTaskStatusRequest $request, Task $task, ChangeTaskStatus $changeStatus): JsonResponse
    {
        $task = $changeStatus($request->user(), $task, $request->status());

        return ApiResponse::ok(TaskResource::make($task->loadMissing(Task::RESOURCE_RELATIONS)), 'Estado actualizado.');
    }

    /** RF-04.12: move to another project with its hours. */
    public function move(MoveTaskRequest $request, Task $task, MoveTask $moveTask): JsonResponse
    {
        $destination = $request->destination();
        Gate::authorize('move', [$task, $destination]);

        $result = $moveTask($request->user(), $task, $destination);
        $task = $result['task']->load(Task::RESOURCE_RELATIONS);

        return ApiResponse::ok([
            'task' => TaskResource::make($task),
            'seconds_moved' => $result['seconds_moved'],
            'unassigned' => $result['unassigned'],
        ], "La tarea ahora es {$task->code} en {$task->project->name}.");
    }

    public function assign(SetTaskAssigneesRequest $request, Task $task, AssignTask $assignTask): JsonResponse
    {
        $task = $assignTask($request->user(), $task, $request->assigneeIds());

        return ApiResponse::ok(TaskResource::make($task->loadMissing(Task::RESOURCE_RELATIONS)), 'Asignación actualizada.');
    }

    /** Change history of the task itself (same shape as the project "Actividad" tab), newest first. */
    public function activity(Task $task, ActivityFeed $activityFeed): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return ActivityResource::collection($activityFeed->forSubject($task));
    }
}
