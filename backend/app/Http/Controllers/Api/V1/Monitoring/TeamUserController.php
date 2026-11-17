<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Actions\SetDiscordUserId;
use App\Domain\Monitoring\Queries\ListPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitoringUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * monitoreo /api/users: the team, to pick a project's developers, and each one's Discord id. Client
 * users never show here (they are listed per client). Everything else about users is managed in
 * newDesk: of monitoreo's PUT only `discord_user_id` stays.
 */
class TeamUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']], ['page' => 'Página inválida.']);
        $team = User::query()->where('company_id', $request->user()->company_id)->whereNull('client_id');
        $page = new ListPage($request->integer('page', 1), $team->count());
        $users = $team->with('role')->orderBy('email')->limit($page->perPage)->offset($page->offset())->get();

        return ApiResponse::page(MonitoringUserResource::collection($users)->resolve(), $page->meta());
    }

    /** `discord_user_id`: absent keeps it, null or "" clears it (D-054). */
    public function update(Request $request, User $user, SetDiscordUserId $setDiscordUserId): JsonResponse
    {
        abort_unless($user->company_id === $request->user()->company_id && $user->client_id === null, 404);
        $data = $request->validate(['discord_user_id' => ['nullable', 'string', 'regex:/^\d{17,20}$/']],
            ['discord_user_id' => 'Pegá el ID numérico de Discord (17 a 20 dígitos).']);
        if ($request->has('discord_user_id')) {
            $user = $setDiscordUserId($user, $data['discord_user_id'] ?? null, $request->user());
        }

        return ApiResponse::ok(MonitoringUserResource::make($user->load('role'))->resolve(), 'Usuario actualizado.');
    }
}
