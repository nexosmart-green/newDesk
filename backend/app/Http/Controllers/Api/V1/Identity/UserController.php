<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\ResendInvitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Queries\UserList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeUserRoleRequest;
use App\Http\Requests\Identity\InviteUserRequest;
use App\Http\Requests\Identity\UserListRequest;
use App\Http\Resources\UserSummaryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(UserListRequest $request, UserList $userList): AnonymousResourceCollection
    {
        return UserSummaryResource::collection($userList($request->user()->company, $request->filters()));
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): JsonResponse
    {
        Gate::authorize('invite', User::class);

        $user = $inviteUser($request->user(), $request->personData(), $request->role());

        return ApiResponse::ok(UserSummaryResource::make($user->load('role')), 'Invitación enviada.', 201);
    }

    public function updateRole(ChangeUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): JsonResponse
    {
        $user = $changeUserRole($request->user(), $user, $request->role());

        return ApiResponse::ok(UserSummaryResource::make($user->load('role')), 'Rol actualizado.');
    }

    public function resendInvitation(Request $request, User $user, ResendInvitation $resendInvitation): JsonResponse
    {
        Gate::authorize('resendInvitation', $user);

        $resendInvitation($request->user(), $user);

        return ApiResponse::ok(message: 'Invitación reenviada.');
    }
}
