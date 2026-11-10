<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\AssignTask;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('assign_task')]
#[Description('Define la lista completa de asignados de una tarea (emails o ids de miembros del proyecto). Requiere permiso tasks.assign.')]
class AssignTaskTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly AssignTask $assignTask) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['code' => 'required|string', 'assignees' => 'present|array|max:20', 'assignees.*' => 'string']);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('assign', $task);

        $task = ($this->assignTask)($user, $task, $this->lookup->userIds($user, $input['assignees']));

        return $this->json(['updated' => McpPresenter::task($task)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'assignees' => $schema->array()->items($schema->string())->description('Emails o ids. Lista vacía = sin asignados.')->required(),
        ];
    }
}
