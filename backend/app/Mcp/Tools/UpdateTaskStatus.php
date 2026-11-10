<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_task_status')]
#[Description('Cambia el estado de una tarea por nombre (ej. "En curso", "Completada"). Pasarla a un estado cerrado detiene los cronómetros que corren sobre ella.')]
class UpdateTaskStatus extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly ChangeTaskStatus $changeStatus) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['code' => 'required|string', 'status' => 'required|string']);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('update', $task);

        $task = ($this->changeStatus)($user, $task, $this->lookup->status($user, $input['status']));

        return $this->json(['updated' => McpPresenter::task($task)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'status' => $schema->string()->description('Nombre del estado.')->required(),
        ];
    }
}
