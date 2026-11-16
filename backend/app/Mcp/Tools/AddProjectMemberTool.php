<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\AddProjectMember;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('add_project_member')]
#[Description('Suma a una persona activa (email o id, sale de list_people) como miembro de un proyecto: ve sus tareas y se le pueden asignar. Si ya es miembro no cambia nada. Requiere permiso members.manage.')]
#[IsIdempotent]
class AddProjectMemberTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly AddProjectMember $addMember) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['project' => 'required|string', 'person' => 'required|string']);
        $project = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('manageMembers', $project);
        $person = $this->lookup->person($user, $input['person']);

        return PreparedWrite::of(
            "Sumar a {$person->fullName()} al proyecto {$project->code_prefix} «{$project->name}»",
            fn () => $this->json([
                'added' => McpManagementPresenter::person(($this->addMember)($user, $project, $person->id)),
                'project' => $project->code_prefix,
            ]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'person' => $schema->string()->description('Email o id de la persona.')->required(),
        ];
    }
}
