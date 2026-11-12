<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\AllocateHours;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('allocate_hours')]
#[Description('Carga o amplía horas de la bolsa de un proyecto (minutos; negativos corrigen). Motivo obligatorio. Requiere permiso hours.allocate.')]
class AllocateHoursTool extends NewDeskTool
{
    /** Same ceiling as the web (seconds), in the minutes this tool takes. */
    private const MAX_MINUTES = TimeTrackingLimits::ALLOCATION_SECONDS_MAX / 60;

    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly AllocateHours $allocateHours) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'project' => 'required|string',
            'minutes' => ['required', 'integer', 'not_in:0', 'between:-'.self::MAX_MINUTES.','.self::MAX_MINUTES],
            'reason' => ['required', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REASON_MAX],
            'reference' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REFERENCE_MAX],
        ]);
        $project = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('allocateHours', $project);

        ($this->allocateHours)($user, $project, $input['minutes'] * 60, $input['reason'], $input['reference'] ?? null);

        return $this->json(['project' => McpPresenter::project($project->fresh())]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'minutes' => $schema->integer()->description('Minutos a cargar (ej. 300 = 5 h).')->required(),
            'reason' => $schema->string()->description('Motivo, ej. "Presupuesto #123".')->required(),
            'reference' => $schema->string(),
        ];
    }
}
