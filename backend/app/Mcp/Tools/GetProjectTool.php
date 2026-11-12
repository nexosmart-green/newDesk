<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project')]
#[Description('Detalle de un proyecto: horas disponibles y miembros (nombre y email, para asignar o mencionar).')]
#[IsReadOnly]
class GetProjectTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup) {}

    protected function run(Request $request, User $user): Response
    {
        $request->validate(['project' => 'required|string']);
        $project = $this->lookup->project($user, $request->get('project'));

        return $this->json([
            ...McpPresenter::project($project),
            'description' => $project->description,
            'members' => $project->members()->active()->get()
                ->map(fn (User $member) => ['name' => $member->fullName(), 'email' => $member->email])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required()];
    }
}
