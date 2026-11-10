<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\RequestHourExtension;
use App\Domain\TimeTracking\Actions\ResolveHourExtension;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-05.08: "pedir ampliación" from the block; `hours.allocate` approves (loading hours) or rejects.
 */
class HourExtensionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize(Permission::HoursAllocate->value);

        return ApiResponse::ok(HourExtensionRequest::query()->where('status', 'pending')->with(['project', 'requester'])->oldest()->get()
            ->map(fn (HourExtensionRequest $request) => $this->present($request)));
    }

    public function store(Request $request, Project $project, RequestHourExtension $requestExtension): JsonResponse
    {
        Gate::authorize('view', $project);
        $request->validate(['message' => ['nullable', 'string', 'max:2000']]);

        $extension = $requestExtension($request->user(), $project, $request->input('message'));

        return ApiResponse::ok($this->present($extension->load(['project', 'requester'])), 'Pedido de ampliación enviado.', 201);
    }

    public function approve(Request $request, HourExtensionRequest $extensionRequest, ResolveHourExtension $resolve): JsonResponse
    {
        $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:36000000'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $extension = $resolve->approve($request->user(), $extensionRequest, $request->integer('seconds'), $request->string('reason')->value(), $request->input('reference'));

        return ApiResponse::ok($this->present($extension->load(['project', 'requester'])), 'Ampliación aprobada.');
    }

    public function reject(Request $request, HourExtensionRequest $extensionRequest, ResolveHourExtension $resolve): JsonResponse
    {
        $extension = $resolve->reject($request->user(), $extensionRequest);

        return ApiResponse::ok($this->present($extension->load(['project', 'requester'])), 'Pedido rechazado.');
    }

    /** @return array<string, mixed> */
    private function present(HourExtensionRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'message' => $request->message,
            'project' => ['id' => $request->project->id, 'name' => $request->project->name],
            'requested_by' => ['id' => $request->requester->id, 'full_name' => $request->requester->fullName()],
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
