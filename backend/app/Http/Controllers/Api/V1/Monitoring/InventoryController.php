<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Monitoring\Queries\ProjectIndex;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * monitoreo GET /api/inventory/projects and /api/portal-admin/folders: the project folders of every
 * server, with the client project each belongs to. Not paginated, as monitoreo.
 */
class InventoryController extends Controller
{
    public function projects(ProjectIndex $index): JsonResponse
    {
        return ApiResponse::ok($index());
    }

    /**
     * GET portal-admin/folders: the same inventory, offered to link folders to a client project.
     * Server tools present on every server (the DB client, nginx error pages) are left out (D-047).
     */
    public function folders(ProjectIndex $index): JsonResponse
    {
        return ApiResponse::ok(array_values(array_filter($index(),
            fn (array $folder) => ! in_array(basename($folder['path']), ['nothinghere!', 'error_templates'], true))));
    }
}
