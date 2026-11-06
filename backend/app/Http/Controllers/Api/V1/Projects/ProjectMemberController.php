<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Projects\Actions\SyncProjectMembers;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectMembersRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function update(ProjectMembersRequest $request, Project $project, SyncProjectMembers $sync): ProjectResource
    {
        Gate::authorize('manageMembers', $project);
        $sync($project, array_map('intval', $request->validated('user_ids')), $request->user());

        return ProjectResource::make($project->load(['client', 'leader', 'members']));
    }
}
