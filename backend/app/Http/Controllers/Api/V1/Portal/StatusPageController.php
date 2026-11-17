<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Queries\ProjectLogo;
use App\Domain\StatusPage\Queries\ResponseTimes;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Http\Controllers\Api\V1\Monitoring\PortalProjectController;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * monitoreo /api/portal/projects (RF-13.07): a client sees only its own active projects, by slug,
 * as parts (Web/App/CMS), never servers, IPs, ports or technical components (CA-13.13).
 */
class StatusPageController extends Controller
{
    public function index(Request $request, StatusPage $page): JsonResponse
    {
        return ApiResponse::ok($page->cards((int) $request->user()->client_id));
    }

    public function show(Request $request, string $slug, StatusPage $page): JsonResponse
    {
        $project = $this->project($request, $slug);

        return ApiResponse::ok($page->view($project, ProjectLogo::url($project, forClient: true)));
    }

    public function logo(Request $request, string $slug): Response
    {
        return ProjectLogo::response($this->project($request, $slug));
    }

    public function responseTimes(Request $request, string $slug, ResponseTimes $times): JsonResponse
    {
        return ApiResponse::ok($times($this->project($request, $slug), PortalProjectController::range($request)));
    }

    private function project(Request $request, string $slug): Project
    {
        return StatusPage::clientProject($request->user(), $slug);
    }
}
