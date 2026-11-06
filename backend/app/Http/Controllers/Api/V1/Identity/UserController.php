<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\ResendInvitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\InviteUserRequest;
use App\Http\Resources\UserSummaryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $request->validate([
            'status' => ['nullable', Rule::in(['invited', 'active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $users = $request->user()->company->users()
            ->with('role')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(50);

        return UserSummaryResource::collection($users)->additional(['message' => null, 'errors' => null]);
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): JsonResponse
    {
        Gate::authorize('invite', User::class);

        $user = $inviteUser($request->user(), $request->personData(), $request->role());

        return ApiResponse::ok(UserSummaryResource::make($user->load('role')), 'Invitación enviada.', 201);
    }

    public function updateRole(Request $request, User $user, ChangeUserRole $changeUserRole): JsonResponse
    {
        Gate::authorize('changeRole', $user);

        $request->validate(['role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('company_id', $request->user()->company_id)]]);

        $user = $changeUserRole($request->user(), $user, Role::query()->findOrFail($request->integer('role_id')));

        return ApiResponse::ok(UserSummaryResource::make($user->load('role')), 'Rol actualizado.');
    }

    public function resendInvitation(Request $request, User $user, ResendInvitation $resendInvitation): JsonResponse
    {
        Gate::authorize('resendInvitation', $user);

        $resendInvitation($request->user(), $user);

        return ApiResponse::ok(message: 'Invitación reenviada.');
    }
}
