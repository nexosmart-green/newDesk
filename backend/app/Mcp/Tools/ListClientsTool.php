<?php

namespace App\Mcp\Tools;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\ClientList;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\McpPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_clients')]
#[Description('Clientes de la empresa por orden alfabético, con su id (lo piden create_project, update_client y search_projects), CUIT, contacto y estado. Filtros: estado y texto. Requiere permiso clients.manage o projects.manage.')]
#[IsReadOnly]
class ListClientsTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleClients;
    }

    public function __construct(private readonly ClientList $clientList) {}

    protected function run(Request $request, User $user): Response
    {
        Gate::forUser($user)->authorize('viewAny', Client::class);
        $input = $request->validate([
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
            'search' => 'nullable|string|max:100',
            ...McpPage::rules(),
        ]);

        $clients = ($this->clientList)(
            ['status' => $input['status'] ?? null, 'search' => $input['search'] ?? null],
            McpPage::limit($request),
            McpPage::page($request),
        );

        return $this->json(McpPage::of($clients, 'clients', fn (Client $client) => McpManagementPresenter::client($client)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(ClientStatus::cases(), 'value')),
            'search' => $schema->string()->description('Parte de la razón social, el nombre de fantasía o el CUIT.'),
            ...McpPage::schema($schema),
        ];
    }
}
