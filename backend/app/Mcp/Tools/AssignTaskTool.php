<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\AssignTask;
use App\Domain\Tasks\Support\TaskLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('assign_task')]
#[Description('Define la lista completa de asignados de una tarea (emails o ids de miembros del proyecto). Requiere permiso tasks.assign.')]
class AssignTaskTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly AssignTask $assignTask) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['code' => 'required|string', 'assignees' => 'present|array|max:'.TaskLimits::ASSIGNEES_MAX, 'assignees.*' => 'string']);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('assign', $task);
        $userIds = $this->lookup->userIds($user, $input['assignees']);
        $names = McpManagementPresenter::names($user->company_id, $userIds);

        return PreparedWrite::of(
            "Dejar {$task->code} asignada a: ".($names === '' ? 'nadie' : $names),
            fn () => $this->json(['updated' => McpPresenter::task(($this->assignTask)($user, $task, $userIds), $user)]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'assignees' => $schema->array()->items($schema->string())->description('Emails o ids. Lista vacía = sin asignados.')->required(),
        ];
    }
}
