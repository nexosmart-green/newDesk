<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\ApproveHourExtension;
use App\Domain\TimeTracking\Actions\RejectHourExtension;
use App\Domain\TimeTracking\Actions\RequestHourExtension;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\ApproveHourExtensionRequest;
use App\Http\Requests\TimeTracking\RequestHourExtensionRequest;
use App\Http\Resources\HourExtensionRequestResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * RF-05.08: "pedir ampliación" from the block; `hours.allocate` approves (loading hours) or rejects.
 */
class HourExtensionController extends Controller
{
    private const RELATIONS = ['project', 'requester'];

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', HourExtensionRequest::class);

        return HourExtensionRequestResource::collection(HourExtensionRequest::query()->pending()->with(self::RELATIONS)->oldest()->get());
    }

    public function store(RequestHourExtensionRequest $request, Project $project, RequestHourExtension $requestExtension): JsonResponse
    {
        $extension = $requestExtension($request->user(), $project, $request->validated('message'));

        return ApiResponse::ok(HourExtensionRequestResource::make($extension->load(self::RELATIONS)), 'Pedido de ampliación enviado.', 201);
    }

    public function approve(ApproveHourExtensionRequest $request, HourExtensionRequest $extensionRequest, ApproveHourExtension $approve): JsonResponse
    {
        $extension = $approve($request->user(), $extensionRequest, $request->integer('seconds'), $request->string('reason')->value(), $request->validated('reference'));

        return ApiResponse::ok(HourExtensionRequestResource::make($extension->load(self::RELATIONS)), 'Ampliación aprobada.');
    }

    public function reject(Request $request, HourExtensionRequest $extensionRequest, RejectHourExtension $reject): JsonResponse
    {
        Gate::authorize('reject', $extensionRequest);

        $extension = $reject($request->user(), $extensionRequest);

        return ApiResponse::ok(HourExtensionRequestResource::make($extension->load(self::RELATIONS)), 'Pedido rechazado.');
    }
}
