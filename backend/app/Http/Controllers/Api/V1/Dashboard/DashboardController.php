<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Domain\Dashboard\Queries\HomeDashboard;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Inicio": no module of its own; each block follows the viewer's modules and permissions.
 */
class DashboardController extends Controller
{
    public function show(Request $request, HomeDashboard $dashboard): JsonResponse
    {
        $data = $dashboard($request->user());

        if ($data['tasks'] !== null) {
            $data['tasks']['due_soon'] = TaskResource::collection($data['tasks']['due_soon']);
        }

        return ApiResponse::ok($data);
    }
}
