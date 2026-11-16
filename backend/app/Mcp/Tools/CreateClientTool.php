<?php

namespace App\Mcp\Tools;

use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Support\ClientRules;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_client')]
#[Description('Da de alta un cliente (razón social obligatoria; CUIT, contacto, email, teléfono y notas opcionales). Antes conviene buscarlo con list_clients para no duplicarlo. Requiere permiso clients.manage.')]
class CreateClientTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleClients;
    }

    public function __construct(private readonly SaveClient $saveClient) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        Gate::forUser($user)->authorize('create', Client::class);
        $data = $request->validate(ClientRules::save());

        return PreparedWrite::of(
            "Dar de alta el cliente «{$data['legal_name']}»".(isset($data['tax_id']) ? " (CUIT {$data['tax_id']})" : ''),
            fn () => $this->json(['created' => McpManagementPresenter::client(($this->saveClient)($user, $data))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return self::fields($schema, legalNameRequired: true);
    }

    /** @return array<string, mixed> */
    public static function fields(JsonSchema $schema, bool $legalNameRequired): array
    {
        $legalName = $schema->string()->description('Razón social.');

        return [
            'legal_name' => $legalNameRequired ? $legalName->required() : $legalName,
            'trade_name' => $schema->string()->description('Nombre de fantasía.'),
            'tax_id' => $schema->string()->description('CUIT, ej. "30-71234567-8".'),
            'contact_name' => $schema->string(),
            'email' => $schema->string(),
            'phone' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }
}
