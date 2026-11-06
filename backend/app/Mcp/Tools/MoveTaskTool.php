<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\MoveTask;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('move_task')]
#[Description('Mueve una tarea a otro proyecto (si se cargó en el equivocado). Sus horas, comentarios y asignados que sean miembros del destino viajan con ella; toma un código nuevo y el viejo sigue funcionando. Si ya tiene horas requiere permiso hours.allocate y que el destino tenga horas disponibles.')]
class MoveTaskTool extends NewDeskTool
{
    public function __construct(private readonly McpLookup $lookup, private readonly MoveTask $moveTask) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['code' => 'required|string', 'project' => 'required|string']);
        $task = $this->lookup->task($user, $input['code']);
        $destination = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('move', [$task, $destination]);

        $result = ($this->moveTask)($user, $task, $destination);

        return $this->json([
            'moved' => McpPresenter::task($result['task']),
            'previous_code' => $input['code'],
            'hours_moved' => McpPresenter::hours($result['seconds_moved']),
            'unassigned' => $result['unassigned'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código actual de la tarea, ej. "bhn-60".')->required(),
            'project' => $schema->string()->description('Prefijo del proyecto destino, ej. "otr".')->required(),
        ];
    }
}
