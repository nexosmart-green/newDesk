<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\UpdateProject;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use App\Mcp\Support\ProjectToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_project')]
#[Description('Edita un proyecto por prefijo: solo cambian los campos que mandes. Para el estado usá change_project_status; para miembros, add_project_member / remove_project_member; para horas, allocate_hours. Requiere permiso projects.manage.')]
class UpdateProjectTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(
        private readonly McpLookup $lookup,
        private readonly ProjectToolInput $input,
        private readonly UpdateProject $updateProject,
    ) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $request->validate(['project' => 'required|string']);
        $project = $this->lookup->project($user, $request->get('project'));
        Gate::forUser($user)->authorize('update', $project);
        ['data' => $data] = $this->input->validated($request, $user, $project);
        $changed = implode(', ', array_keys($data));

        return PreparedWrite::of(
            "Editar el proyecto {$project->code_prefix} «{$project->name}»: ".($changed === '' ? 'sin cambios' : $changed),
            fn () => $this->json(['updated' => McpManagementPresenter::project(($this->updateProject)($user, $project, $data))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo actual del proyecto, ej. "bhn".')->required(),
            ...ProjectToolInput::schema($schema, creating: false),
        ];
    }
}
