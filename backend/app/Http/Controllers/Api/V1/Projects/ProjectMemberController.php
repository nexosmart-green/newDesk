<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\AddProjectMember;
use App\Domain\Projects\Actions\RemoveProjectMember;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\AddProjectMemberRequest;
use App\Http\Resources\PersonResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-03.03: only members see the project and can receive its tasks.
 */
class ProjectMemberController extends Controller
{
    public function store(AddProjectMemberRequest $request, Project $project, AddProjectMember $addMember): JsonResponse
    {
        $member = $addMember($request->user(), $project, $request->userId());

        return ApiResponse::ok(PersonResource::make($member), 'Miembro agregado.', 201);
    }

    public function destroy(Request $request, Project $project, User $user, RemoveProjectMember $removeMember): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $removeMember($request->user(), $project, $user);

        return ApiResponse::ok(message: "{$user->fullName()} ya no es miembro de {$project->name}.");
    }
}
