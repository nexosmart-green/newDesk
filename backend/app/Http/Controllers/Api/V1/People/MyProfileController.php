<?php

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\People\Actions\SetUserSkills;
use App\Domain\People\Actions\UpdateOwnProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\UpdateOwnProfileRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\ProfileResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi perfil": what every person manages about themselves (RF-01.05/06/07/09).
 */
class MyProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok(ProfileResource::make($request->user()));
    }

    public function update(UpdateOwnProfileRequest $request, UpdateOwnProfile $updateOwnProfile): JsonResponse
    {
        return ApiResponse::ok(ProfileResource::make($updateOwnProfile($request->user(), $request->validated())), 'Perfil actualizado.');
    }

    public function avatar(Request $request, ReplaceAvatar $replaceAvatar): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'max:5120']]);

        return ApiResponse::ok(ProfileResource::make($replaceAvatar($request->user(), $request->file('avatar'))), 'Foto actualizada.');
    }

    public function skills(Request $request, SetUserSkills $setSkills): JsonResponse
    {
        $request->validate([
            'skills' => ['present', 'array', 'max:50'],
            'skills.*.id' => ['required', 'integer', 'distinct'],
            'skills.*.level' => ['required', 'integer', 'between:0,100'],
        ]);

        $levels = collect($request->input('skills'))->mapWithKeys(fn (array $skill) => [(int) $skill['id'] => (int) $skill['level']])->all();

        return ApiResponse::ok(ProfileResource::make($setSkills($request->user(), $levels)), 'Habilidades guardadas.');
    }

    public function team(Request $request): JsonResponse
    {
        $team = $request->user()->directReports()->where('users.status', 'active')
            ->with(['profile.jobTitle', 'profile.area'])
            ->orderBy('first_name')->get();

        return ApiResponse::ok(PersonResource::collection($team));
    }
}
