<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectList;
use App\Domain\Shared\Activity\ActivityLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectList $projectList): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'running_out' => ['nullable', 'boolean'],
        ]);
        $filters['client_id'] = isset($filters['client_id']) ? (int) $filters['client_id'] : null;
        $filters['running_out'] = $request->boolean('running_out');

        return ProjectSummaryResource::collection($projectList($request->user(), $filters))
            ->additional(['message' => null, 'errors' => null]);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return ApiResponse::ok(ProjectResource::make($project));
    }

    public function store(SaveProjectRequest $request, CreateProject $createProject): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $project = $createProject($request->user(), $request->projectData(), $request->memberIds());

        return ApiResponse::ok(ProjectResource::make($project), 'Proyecto creado.', 201);
    }

    public function update(SaveProjectRequest $request, Project $project, UpdateProject $updateProject): JsonResponse
    {
        Gate::authorize('update', $project);

        return ApiResponse::ok(ProjectResource::make($updateProject($request->user(), $project, $request->projectData())), 'Proyecto actualizado.');
    }

    public function updateStatus(Request $request, Project $project, ChangeProjectStatus $changeStatus): JsonResponse
    {
        Gate::authorize('update', $project);
        $request->validate(['status' => ['required', Rule::enum(ProjectStatus::class)]]);

        $project = $changeStatus($request->user(), $project, ProjectStatus::from($request->string('status')->value()));

        return ApiResponse::ok(ProjectResource::make($project), 'Estado actualizado.');
    }

    /** RF-03.06 "Actividad" tab: immutable log, newest first. */
    public function activity(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $entries = ActivityLog::query()->where('project_id', $project->id)->with('actor')->latest('id')->paginate(50);

        return ActivityResource::collection($entries)->additional(['message' => null, 'errors' => null]);
    }
}
