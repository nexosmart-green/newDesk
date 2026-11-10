<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\CreateRole;
use App\Domain\Identity\Actions\DeleteRole;
use App\Domain\Identity\Actions\UpdateRole;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\SaveRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Editable roles made of granular permissions (RF-01.03, P-02).
 * Short list per company: not paginated on purpose.
 */
class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        return RoleResource::collection(Role::query()->withCount('users')->orderBy('name')->get())
            ->additional(['message' => null, 'errors' => null]);
    }

    public function permissions(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        return ApiResponse::ok(array_map(
            fn (Permission $permission) => ['value' => $permission->value, 'label' => $permission->label()],
            Permission::cases(),
        ));
    }

    public function store(SaveRoleRequest $request, CreateRole $createRole): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $role = $createRole($request->user(), $request->string('name'), $request->permissions());

        return ApiResponse::ok(RoleResource::make($role), 'Rol creado.', 201);
    }

    public function update(SaveRoleRequest $request, Role $role, UpdateRole $updateRole): JsonResponse
    {
        Gate::authorize('update', $role);

        $role = $updateRole($request->user(), $role, $request->string('name'), $request->permissions());

        return ApiResponse::ok(RoleResource::make($role), 'Rol actualizado.');
    }

    public function destroy(Request $request, Role $role, DeleteRole $deleteRole): JsonResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole($request->user(), $role);

        return ApiResponse::ok(message: 'Rol borrado.');
    }
}
