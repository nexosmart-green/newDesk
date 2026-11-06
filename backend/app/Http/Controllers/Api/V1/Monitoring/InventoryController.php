<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Queries\ProjectIndex;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * monitoreo GET /api/inventory/projects: the project folders of every server, for the search
 * on the servers page. Not paginated, as monitoreo.
 */
class InventoryController extends Controller
{
    public function projects(ProjectIndex $index): JsonResponse
    {
        return ApiResponse::ok($index());
    }
}
