<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Domain\TimeTracking\Queries\PendingExtensionRequests;
use App\Mcp\Support\McpManagementPresenter;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_extension_requests')]
#[Description('Pedidos de ampliación de horas pendientes (los más viejos primero), con el proyecto, quién lo pidió, su mensaje y las horas actuales de la bolsa. Se resuelven con approve_extension_request o reject_extension_request. Requiere permiso hours.allocate.')]
#[IsReadOnly]
class ListExtensionRequestsTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly PendingExtensionRequests $pendingRequests) {}

    protected function run(Request $request, User $user): Response
    {
        Gate::forUser($user)->authorize('viewAny', HourExtensionRequest::class);

        $pending = ($this->pendingRequests)($user->company_id);

        return $this->json([
            'total' => $pending->count(),
            'requests' => $pending->map(fn (HourExtensionRequest $extension) => McpManagementPresenter::extensionRequest($extension))->all(),
        ]);
    }
}
