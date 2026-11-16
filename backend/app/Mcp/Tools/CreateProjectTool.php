<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Models\Project;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use App\Mcp\Support\ProjectToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_project')]
#[Description('Crea un proyecto (nombre, prefijo, tipo; cliente, responsable, fechas y miembros opcionales). Arranca sin horas: cargalas después con allocate_hours. Requiere permiso projects.manage.')]
class CreateProjectTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly ProjectToolInput $input, private readonly CreateProject $createProject) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        Gate::forUser($user)->authorize('create', Project::class);
        ['data' => $data, 'member_ids' => $memberIds] = $this->input->validated($request, $user);

        return PreparedWrite::of(
            "Crear el proyecto «{$data['name']}» (prefijo ".mb_strtolower($data['code_prefix']).", {$this->typeLabel($data['type'])})",
            fn () => $this->json(['created' => McpManagementPresenter::project(($this->createProject)($user, $data, $memberIds))]),
        );
    }

    private function typeLabel(string $type): string
    {
        return $type === 'bag' ? 'bolsa de horas' : 'interno';
    }

    public function schema(JsonSchema $schema): array
    {
        return ProjectToolInput::schema($schema, creating: true);
    }
}
