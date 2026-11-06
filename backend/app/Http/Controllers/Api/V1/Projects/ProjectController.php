<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            Project::query()
                ->visibleTo($request->user())
                ->with(['client', 'leader'])
                ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(ProjectRequest $request, SaveProject $save): JsonResponse
    {
        Gate::authorize('create', Project::class);
        $project = $save($request->validated(), $request->user());

        return ProjectResource::make($project->load(['client', 'leader', 'members']))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return ProjectResource::make($project->load(['client', 'leader', 'members']));
    }

    public function update(ProjectRequest $request, Project $project, SaveProject $save): ProjectResource
    {
        Gate::authorize('update', $project);

        return ProjectResource::make($save($request->validated(), $request->user(), $project)->load(['client', 'leader', 'members']));
    }

    public function destroy(Request $request, Project $project, DeleteProject $delete): JsonResponse
    {
        Gate::authorize('delete', $project);
        $delete($project, $request->user());

        return ApiResponse::ok(message: 'Proyecto eliminado');
    }
}
