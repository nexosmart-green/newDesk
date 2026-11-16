<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Support\Duration;
use App\Domain\TimeTracking\Actions\ApproveHourExtension;
use App\Domain\TimeTracking\Support\EnsureProjectHasBag;
use App\Domain\TimeTracking\Support\PendingExtensionLock;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('approve_extension_request')]
#[Description('Aprueba un pedido de ampliación pendiente (id de list_extension_requests) cargando horas a la bolsa de su proyecto, con motivo obligatorio. Requiere permiso hours.allocate.')]
class ApproveExtensionRequestTool extends NewDeskWriteTool
{
    /** Same ceiling as the web (seconds), in the minutes this tool takes. */
    private const MAX_MINUTES = TimeTrackingLimits::ALLOCATION_SECONDS_MAX / 60;

    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(
        private readonly McpLookup $lookup,
        private readonly EnsureProjectHasBag $ensureBag,
        private readonly ApproveHourExtension $approve,
    ) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate([
            'request_id' => 'required|integer',
            'minutes' => ['required', 'integer', 'between:1,'.self::MAX_MINUTES],
            'reason' => ['required', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REASON_MAX],
            'reference' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REFERENCE_MAX],
        ]);
        $extension = $this->lookup->extensionRequest($user, (int) $input['request_id']);
        Gate::forUser($user)->authorize('approve', $extension);
        PendingExtensionLock::ensurePending($extension);
        ($this->ensureBag)($extension->project);
        $seconds = $input['minutes'] * 60;

        return PreparedWrite::of(
            "Aprobar el pedido #{$extension->id} de {$extension->requester->fullName()} cargando ".Duration::humanize($seconds)
                ." a {$extension->project->code_prefix} «{$extension->project->name}» — motivo: {$input['reason']}",
            fn () => $this->json(['approved' => McpManagementPresenter::extensionRequest(
                ($this->approve)($user, $extension, $seconds, $input['reason'], $input['reference'] ?? null)->refresh(),
            )]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->integer()->description('Id del pedido (sale de list_extension_requests).')->required(),
            'minutes' => $schema->integer()->description('Minutos a cargar (ej. 1200 = 20 h).')->required(),
            'reason' => $schema->string()->description('Motivo, ej. "Presupuesto #123".')->required(),
            'reference' => $schema->string(),
        ];
    }
}
