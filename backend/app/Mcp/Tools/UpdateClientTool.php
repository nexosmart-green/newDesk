<?php

namespace App\Mcp\Tools;

use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Support\ClientRules;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_client')]
#[Description('Edita los datos de un cliente por id (sale de list_clients). Solo cambian los campos que mandes; para borrar uno mandalo vacío. Para activarlo o inactivarlo usá change_client_status. Requiere permiso clients.manage.')]
class UpdateClientTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleClients;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly SaveClient $saveClient) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $request->validate(['client_id' => 'required|integer']);
        $client = $this->lookup->client($user, (int) $request->get('client_id'));
        Gate::forUser($user)->authorize('update', $client);

        $changes = Arr::only($request->all(), Client::EDITABLE);
        // Same rules as the web form, applied to the client as it would end up (the web always sends every field).
        $data = Validator::make([...$client->only(Client::EDITABLE), ...$changes], ClientRules::save())->validate();
        $changed = implode(', ', array_keys($changes));

        return PreparedWrite::of(
            "Editar el cliente «{$client->displayName()}»: ".($changed === '' ? 'sin cambios' : $changed),
            fn () => $this->json(['updated' => McpManagementPresenter::client(($this->saveClient)($user, $data, $client))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Id del cliente (sale de list_clients).')->required(),
            ...CreateClientTool::fields($schema, legalNameRequired: false),
        ];
    }
}
