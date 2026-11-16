<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskList;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPage;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('Tareas abiertas de todo el equipo ("Todas las tareas") en los proyectos que puedo ver. Filtros: proyecto, persona asignada, solo vencidas, estado, texto. Para solo las mías usá list_my_tasks.')]
#[IsReadOnly]
class ListTasksTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly TaskList $taskList, private readonly McpLookup $lookup) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'project' => 'nullable|string',
            'assignee' => 'nullable|string',
            'overdue' => 'nullable|boolean',
            'status' => 'nullable|string',
            'search' => 'nullable|string|max:100',
            ...McpPage::rules(),
        ]);

        $tasks = ($this->taskList)($user, null, [
            'mine' => false,
            'overdue' => (bool) ($input['overdue'] ?? false),
            'project_id' => isset($input['project']) ? $this->lookup->project($user, $input['project'])->id : null,
            'assignee_id' => isset($input['assignee']) ? $this->lookup->userIds($user, [$input['assignee']])[0] : null,
            'status_ids' => isset($input['status']) ? [$this->lookup->status($user, $input['status'])->id] : [],
            'search' => $input['search'] ?? null,
        ], McpPage::limit($request), McpPage::page($request));

        return $this->json(McpPage::of($tasks, 'tasks', fn (Task $task) => McpPresenter::task($task, $user)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".'),
            'assignee' => $schema->string()->description('Email o id de la persona asignada.'),
            'overdue' => $schema->boolean()->description('Solo tareas vencidas.'),
            'status' => $schema->string()->description('Nombre exacto del estado, ej. "En curso".'),
            'search' => $schema->string()->description('Parte del título o del código.'),
            ...McpPage::schema($schema),
        ];
    }
}
