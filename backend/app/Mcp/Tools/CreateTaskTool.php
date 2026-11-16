<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Support\TaskLimits;
use App\Domain\TimeTracking\Actions\CreateAndStartTask;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_task')]
#[Description('Crea una tarea en un proyecto donde soy miembro. Con start_timer=true la asigna a mí y arranca mi cronómetro ("Crear e iniciar"); si ya tengo uno corriendo hay que mandar previous_comment (qué hice, mínimo 10 caracteres).')]
class CreateTaskTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(
        private readonly McpLookup $lookup,
        private readonly CreateTask $createTask,
        private readonly CreateAndStartTask $createAndStart,
    ) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'project' => 'required|string',
            'title' => 'required|string|max:'.TaskLimits::TITLE_MAX,
            'description' => 'nullable|string|max:'.TaskLimits::DESCRIPTION_MAX,
            'assignees' => 'nullable|array|max:'.TaskLimits::ASSIGNEES_MAX,
            'assignees.*' => 'string',
            'due_on' => 'nullable|date_format:Y-m-d',
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'start_timer' => 'nullable|boolean',
            'previous_comment' => 'nullable|string|max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX,
        ]);

        $project = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        $data = array_filter(['title' => $input['title'], 'description' => $input['description'] ?? null, 'due_on' => $input['due_on'] ?? null, 'priority' => $input['priority'] ?? null]);
        $assignees = $this->lookup->userIds($user, $input['assignees'] ?? []);

        if (! ($input['start_timer'] ?? false)) {
            $task = ($this->createTask)($user, $project, $data, $assignees);

            return $this->json(['created' => McpPresenter::task($task, $user)]);
        }

        $result = ($this->createAndStart)($user, $project, $data, $assignees, $input['previous_comment'] ?? null);

        return $this->json([
            'created' => McpPresenter::task($result['task'], $user),
            'timer' => $result['entry'] ? McpPresenter::entry($result['entry']) : null,
            'timer_not_started_because' => $result['timer_error']?->getMessage(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Markdown.'),
            'assignees' => $schema->array()->items($schema->string())->description('Emails (o ids) de miembros del proyecto.'),
            'due_on' => $schema->string()->description('Vencimiento YYYY-MM-DD.'),
            'priority' => $schema->string()->enum(array_column(TaskPriority::cases(), 'value')),
            'start_timer' => $schema->boolean()->description('Crear e iniciar mi cronómetro.'),
            'previous_comment' => $schema->string()->description('Qué hice en el cronómetro que está corriendo (si hay uno).'),
        ];
    }
}
