<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Actions\AddToRoadmap;
use App\Domain\Support\Actions\RemoveFromRoadmap;
use App\Domain\Support\Actions\ReorderRoadmap;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Queries\Roadmap;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * monitoreo /api/portal-admin/roadmap (D-064, D-066, CA-13.19): always the roadmap of whoever asks,
 * with no way to ask for someone else's. Every write answers with the whole roadmap, as GET does.
 */
class RoadmapController extends Controller
{
    public function index(Request $request, Roadmap $roadmap): JsonResponse
    {
        return ApiResponse::ok($roadmap($request->user()));
    }

    public function store(Request $request, AddToRoadmap $add, Roadmap $roadmap): JsonResponse
    {
        $id = $request->validate(['report_id' => ['required', 'integer']], ['report_id' => 'Reporte inválido.'])['report_id'];
        $report = Report::query()->find($id) ?? throw new BusinessRuleViolation('Reporte no encontrado.', 'not_found', 404);
        $add($request->user(), $report);

        return ApiResponse::ok($roadmap($request->user()), 'Sumado a tu roadmap.');
    }

    public function reorder(Request $request, ReorderRoadmap $reorder, Roadmap $roadmap): JsonResponse
    {
        $ids = $request->validate(['report_ids' => ['present', 'array'], 'report_ids.*' => ['integer']], ['report_ids' => 'Lista inválida.', 'report_ids.*' => 'Lista inválida.'])['report_ids'];
        $reorder($request->user(), array_map('intval', $ids));

        return ApiResponse::ok($roadmap($request->user()));
    }

    public function destroy(Request $request, int $report, RemoveFromRoadmap $remove, Roadmap $roadmap): JsonResponse
    {
        $remove($request->user(), $report);

        return ApiResponse::ok($roadmap($request->user()), 'Quitado de tu roadmap.');
    }
}
