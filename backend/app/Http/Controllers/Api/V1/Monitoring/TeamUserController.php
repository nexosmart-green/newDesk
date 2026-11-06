<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Queries\ListPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitoringUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * monitoreo GET /api/users: the team, to pick a project's developers. Client users never show here
 * (they are listed per client). Users are managed in newDesk, so this is read-only.
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
}
