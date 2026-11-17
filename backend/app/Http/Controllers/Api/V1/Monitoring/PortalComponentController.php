<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Actions\CheckComponents;
use App\Domain\StatusPage\Actions\DeleteComponent;
use App\Domain\StatusPage\Actions\MeasurePageSpeed;
use App\Domain\StatusPage\Actions\SaveComponent;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Queries\MonitorTargets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\ComponentRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** monitoreo /api/portal-admin/…/components and /targets (RF-13.06, D-039). */
class PortalComponentController extends Controller
{
    public function store(ComponentRequest $request, Project $project, SaveComponent $save): JsonResponse
    {
        $component = $save($request->componentInput(), $request->user(), $project);

        return ApiResponse::ok($component->toAdminPayload(), 'Componente creado.', 201);
    }

    public function update(ComponentRequest $request, Component $component, SaveComponent $save): JsonResponse
    {
        $component = $save($request->componentInput(), $request->user(), $component->project, $component);

        return ApiResponse::ok($component->toAdminPayload(), 'Componente actualizado.');
    }

    public function destroy(Request $request, Component $component, DeleteComponent $delete): JsonResponse
    {
        $delete($component, $request->user());

        return ApiResponse::ok(message: 'Componente eliminado.');
    }

    /** "Chequear ahora". */
    public function check(Component $component, CheckComponents $check): JsonResponse
    {
        return ApiResponse::ok($check->one($component)->toAdminPayload());
    }

    /** "Medir ahora": in the background, both strategies. */
    public function pageSpeed(Component $component, MeasurePageSpeed $measure): JsonResponse
    {
        $measure->queue($component);

        return ApiResponse::ok(message: 'Midiendo: el resultado aparece en un par de minutos.', status: 202);
    }

    public function targets(MonitorTargets $targets): JsonResponse
    {
        return ApiResponse::ok($targets());
    }
}
