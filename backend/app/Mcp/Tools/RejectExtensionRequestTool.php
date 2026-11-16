<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\RejectHourExtension;
use App\Domain\TimeTracking\Support\PendingExtensionLock;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('reject_extension_request')]
#[Description('Rechaza un pedido de ampliación pendiente (id de list_extension_requests) sin cargar horas. Requiere permiso hours.allocate.')]
#[IsDestructive]
class RejectExtensionRequestTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly RejectHourExtension $reject) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['request_id' => 'required|integer']);
        $extension = $this->lookup->extensionRequest($user, (int) $input['request_id']);
        Gate::forUser($user)->authorize('reject', $extension);
        PendingExtensionLock::ensurePending($extension);

        return PreparedWrite::of(
            "Rechazar el pedido #{$extension->id} de {$extension->requester->fullName()} para {$extension->project->code_prefix} «{$extension->project->name}»",
            fn () => $this->json(['rejected' => McpManagementPresenter::extensionRequest(($this->reject)($user, $extension)->refresh())]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->integer()->description('Id del pedido (sale de list_extension_requests).')->required(),
        ];
    }
}
