<?php

namespace App\Mcp\Tools;

use App\Domain\Clients\Actions\ChangeClientStatus;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
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

#[Name('change_client_status')]
#[Description('Activa o inactiva un cliente por id. Un cliente inactivo deja de ofrecerse al crear proyectos; sus proyectos siguen como están. Requiere permiso clients.manage.')]
#[IsDestructive]
#[IsIdempotent]
class ChangeClientStatusTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleClients;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly ChangeClientStatus $changeStatus) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['client_id' => 'required|integer', 'status' => ['required', Rule::enum(ClientStatus::class)]]);
        $client = $this->lookup->client($user, (int) $input['client_id']);
        Gate::forUser($user)->authorize('update', $client);
        $status = ClientStatus::from($input['status']);

        return PreparedWrite::of(
            ($status === ClientStatus::Active ? 'Activar' : 'Inactivar')." el cliente «{$client->displayName()}»",
            fn () => $this->json(['updated' => McpManagementPresenter::client(($this->changeStatus)($user, $client, $status))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Id del cliente (sale de list_clients).')->required(),
            'status' => $schema->string()->enum(array_column(ClientStatus::cases(), 'value'))->required(),
        ];
    }
}
