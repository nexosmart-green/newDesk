<?php

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\Identity\Models\User;
use App\Domain\People\Actions\DeactivateUser;
use App\Domain\People\Actions\ReactivateUser;
use App\Domain\People\Actions\UpdateEmployeeProfile;
use App\Domain\People\Queries\PeopleDirectory;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\UpdateEmployeeProfileRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\ProfileResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PeopleController extends Controller
{
    public function index(Request $request, PeopleDirectory $directory): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'area_id' => ['nullable', 'integer'],
            'skill_id' => ['nullable', 'integer'],
            'min_level' => ['nullable', 'integer', 'between:0,100'],
            'include_inactive' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);
        $filters['include_inactive'] = $request->boolean('include_inactive') && $request->user()->can('viewAny', User::class);
        foreach (['area_id', 'skill_id', 'min_level'] as $integer) {
            $filters[$integer] = isset($filters[$integer]) ? (int) $filters[$integer] : null;
        }

        return PersonResource::collection($directory($request->user()->company_id, $filters, $request->integer('per_page', 50)))
            ->additional(['message' => null, 'errors' => null]);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('viewProfile', $user);

        return ApiResponse::ok(ProfileResource::make($user));
    }

    public function update(UpdateEmployeeProfileRequest $request, User $user, UpdateEmployeeProfile $updateProfile): JsonResponse
    {
        Gate::authorize('updateProfile', $user);

        return ApiResponse::ok(ProfileResource::make($updateProfile($request->user(), $user, $request->validated())), 'Perfil actualizado.');
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivate): JsonResponse
    {
        Gate::authorize('deactivate', $user);

        return ApiResponse::ok(PersonResource::make($deactivate($request->user(), $user)), 'Usuario dado de baja.');
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivate): JsonResponse
    {
        Gate::authorize('deactivate', $user);

        return ApiResponse::ok(PersonResource::make($reactivate($request->user(), $user)), 'Usuario reactivado.');
    }
}
