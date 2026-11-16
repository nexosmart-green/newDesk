<?php

namespace App\Mcp\Tools;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectList;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\McpPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_projects')]
#[Description('Todos los proyectos que puedo ver (quien gestiona proyectos ve todos), con cliente, líder y horas cargadas/consumidas/disponibles. running_out=true trae las bolsas por agotarse (misma definición que el panel de Inicio). Para "los proyectos donde soy miembro" usá list_projects.')]
#[IsReadOnly]
class SearchProjectsTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly ProjectList $projectList) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'running_out' => 'nullable|boolean',
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'client_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            ...McpPage::rules(),
        ]);
        if (isset($input['client_id'])) {
            Client::query()->forCompany($user->company_id)->findOrFail($input['client_id']);
        }

        $projects = ($this->projectList)($user, [
            'running_out' => (bool) ($input['running_out'] ?? false),
            'status' => $input['status'] ?? null,
            'client_id' => isset($input['client_id']) ? (int) $input['client_id'] : null,
            'search' => $input['search'] ?? null,
        ], McpPage::limit($request), McpPage::page($request));

        return $this->json(McpPage::of($projects, 'projects', fn (Project $project) => McpManagementPresenter::project($project)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'running_out' => $schema->boolean()->description('Solo bolsas por agotarse.'),
            'status' => $schema->string()->enum(array_column(ProjectStatus::cases(), 'value')),
            'client_id' => $schema->integer()->description('Id del cliente (sale de list_clients).'),
            'search' => $schema->string()->description('Parte del nombre o del prefijo.'),
            ...McpPage::schema($schema),
        ];
    }
}
