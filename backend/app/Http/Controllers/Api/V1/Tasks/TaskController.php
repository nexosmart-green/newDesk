<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Actions\MoveTask;
use App\Domain\Tasks\Actions\UpdateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use App\Domain\Tasks\Queries\TaskList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Http\Requests\Tasks\TaskListRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /** Project "Tareas" tab: list and board over the same data (RF-04.04). */
    public function index(TaskListRequest $request, Project $project, TaskList $taskList): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return $this->listResponse($taskList($request->user(), $project, $request->filters(), $request->perPage()));
    }

    /** "Mis tareas" cross-project (RF-04.07). */
    public function mine(TaskListRequest $request, TaskList $taskList): AnonymousResourceCollection
    {
        $filters = [...$request->filters(), 'mine' => true];

        return $this->listResponse($taskList($request->user(), null, $filters, $request->perPage()));
    }

    /** "Todas las tareas": every task of the projects the viewer can see. */
    public function all(TaskListRequest $request, TaskList $taskList): AnonymousResourceCollection
    {
        return $this->listResponse($taskList($request->user(), null, $request->filters(), $request->perPage()));
    }

    public function store(SaveTaskRequest $request, Project $project, CreateTask $createTask): JsonResponse
    {
        Gate::authorize('create', [Task::class, $project]);

        $task = $createTask($request->user(), $project, $request->taskData(), $request->assigneeIds());

        return ApiResponse::ok(TaskResource::make($task->load(Task::RESOURCE_RELATIONS)), "Tarea {$task->code} creada.", 201);
    }

    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return ApiResponse::ok(TaskResource::make($task->load([...Task::RESOURCE_RELATIONS, 'creator'])));
    }

    public function update(SaveTaskRequest $request, Task $task, UpdateTask $updateTask): JsonResponse
    {
        Gate::authorize('update', $task);

        $task = $updateTask($request->user(), $task, $request->taskData());

        return ApiResponse::ok(TaskResource::make($task->load(Task::RESOURCE_RELATIONS)), 'Tarea actualizada.');
    }

    public function updateStatus(Request $request, Task $task, ChangeTaskStatus $changeStatus): JsonResponse
    {
        Gate::authorize('update', $task);
        $request->validate(['status_id' => ['required', 'integer']]);

        $status = TaskStatus::query()->findOrFail($request->integer('status_id'));
        $task = $changeStatus($request->user(), $task, $status);

        return ApiResponse::ok(TaskResource::make($task->loadMissing(Task::RESOURCE_RELATIONS)), 'Estado actualizado.');
    }

    /** RF-04.12: move to another project with its hours. */
    public function move(Request $request, Task $task, MoveTask $moveTask): JsonResponse
    {
        $request->validate(['project_id' => ['required', 'integer']]);
        $destination = Project::query()->findOrFail($request->integer('project_id'));
        Gate::authorize('move', [$task, $destination]);

        $result = $moveTask($request->user(), $task, $destination);
        $task = $result['task']->load(Task::RESOURCE_RELATIONS);

        return ApiResponse::ok([
            'task' => TaskResource::make($task),
            'seconds_moved' => $result['seconds_moved'],
            'unassigned' => $result['unassigned'],
        ], "La tarea ahora es {$task->code} en {$task->project->name}.");
    }

    public function assign(Request $request, Task $task, AssignTask $assignTask): JsonResponse
    {
        Gate::authorize('assign', $task);
        $request->validate(['assignee_ids' => ['present', 'array', 'max:20'], 'assignee_ids.*' => ['integer', 'distinct']]);

        $task = $assignTask($request->user(), $task, array_map('intval', $request->input('assignee_ids')));

        return ApiResponse::ok(TaskResource::make($task->loadMissing(Task::RESOURCE_RELATIONS)), 'Asignación actualizada.');
    }

    /** Change history of the task itself (same shape as the project "Actividad" tab), newest first. */
    public function activity(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $entries = ActivityLog::query()
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->with('actor')
            ->latest('id')
            ->paginate(50);

        return ActivityResource::collection($entries)->additional(['message' => null, 'errors' => null]);
    }

    private function listResponse(mixed $tasks): AnonymousResourceCollection
    {
        return TaskResource::collection($tasks)->additional(['message' => null, 'errors' => null]);
    }
}
