<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('change_project_status')]
#[Description('Cambia el estado de un proyecto (active, paused, finished, cancelled). Finalizarlo o cancelarlo detiene los cronómetros que corren en él; reabrir uno finalizado o cancelado requiere permiso projects.reopen. Requiere permiso projects.manage.')]
#[IsDestructive]
#[IsIdempotent]
class ChangeProjectStatusTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly ChangeProjectStatus $changeStatus) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['project' => 'required|string', 'status' => ['required', Rule::enum(ProjectStatus::class)]]);
        $project = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('update', $project);
        $status = ProjectStatus::from($input['status']);

        return PreparedWrite::of(
            "Pasar el proyecto {$project->code_prefix} «{$project->name}» de «{$project->status->label()}» a «{$status->label()}»",
            fn () => $this->json(['updated' => McpManagementPresenter::project(($this->changeStatus)($user, $project, $status))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'status' => $schema->string()->enum(array_column(ProjectStatus::cases(), 'value'))->required(),
        ];
    }
}
