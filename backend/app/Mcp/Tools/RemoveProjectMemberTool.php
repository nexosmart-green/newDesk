<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\RemoveProjectMember;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('remove_project_member')]
#[Description('Quita a una persona (email o id) de los miembros de un proyecto: deja de verlo y, si tenía un cronómetro corriendo en él, se corta. Al responsable no se lo puede quitar sin antes cambiarlo con update_project. Requiere permiso members.manage.')]
#[IsDestructive]
#[IsIdempotent]
class RemoveProjectMemberTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly RemoveProjectMember $removeMember) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['project' => 'required|string', 'person' => 'required|string']);
        $project = $this->lookup->project($user, $input['project']);
        Gate::forUser($user)->authorize('manageMembers', $project);
        $person = $this->lookup->person($user, $input['person']);

        return PreparedWrite::of("Quitar a {$person->fullName()} del proyecto {$project->code_prefix} «{$project->name}»", function () use ($user, $project, $person) {
            ($this->removeMember)($user, $project, $person);

            return $this->json(['removed' => $person->fullName(), 'project' => $project->code_prefix]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'person' => $schema->string()->description('Email o id de la persona.')->required(),
        ];
    }
}
