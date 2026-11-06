<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskList;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_my_tasks')]
#[Description('Mis tareas abiertas en todos los proyectos ("Mis tareas"). Filtros opcionales: proyecto (prefijo), solo vencidas, estado.')]
#[IsReadOnly]
class ListMyTasks extends NewDeskTool
{
    public function __construct(private readonly TaskList $taskList, private readonly McpLookup $lookup) {}

    protected function run(Request $request, User $user): Response
    {
        $request->validate(['project' => 'nullable|string', 'overdue' => 'nullable|boolean', 'status' => 'nullable|string']);

        $filters = [
            'mine' => true,
            'overdue' => (bool) $request->get('overdue', false),
            'project_id' => $request->get('project') ? $this->lookup->project($user, $request->get('project'))->id : null,
            'status_ids' => $request->get('status') ? [$this->lookup->status($user, $request->get('status'))->id] : [],
        ];

        $tasks = ($this->taskList)($user, null, $filters, 100);

        return $this->json([
            'total' => $tasks->total(),
            'tasks' => collect($tasks->items())->map(fn (Task $task) => McpPresenter::task($task))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".'),
            'overdue' => $schema->boolean()->description('Solo tareas vencidas.'),
            'status' => $schema->string()->description('Nombre exacto del estado, ej. "En curso".'),
        ];
    }
}
