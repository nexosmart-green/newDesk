<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectList;
use App\Domain\Shared\Activity\ActivityFeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ChangeProjectStatusRequest;
use App\Http\Requests\Projects\ProjectListRequest;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(ProjectListRequest $request, ProjectList $projectList): AnonymousResourceCollection
    {
        return ProjectSummaryResource::collection($projectList($request->user(), $request->filters()));
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

    public function updateStatus(ChangeProjectStatusRequest $request, Project $project, ChangeProjectStatus $changeStatus): JsonResponse
    {
        $project = $changeStatus($request->user(), $project, $request->status());

        return ApiResponse::ok(ProjectResource::make($project), 'Estado actualizado.');
    }

    /** RF-03.06 "Actividad" tab: immutable log, newest first. */
    public function activity(Project $project, ActivityFeed $activityFeed): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return ActivityResource::collection($activityFeed->forProject($project->id));
    }
}
