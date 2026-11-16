<?php

namespace App\Mcp\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;

/**
 * Arguments of create_project / update_project. The model names people by e-mail, the web by id: they are
 * resolved first and then validated with the SAME rules as the web form (ProjectRules).
 */
final class ProjectToolInput
{
    public function __construct(private readonly McpLookup $lookup) {}

    /** @return array{data: array<string, mixed>, member_ids: list<int>} validated, in the shape of the web form */
    public function validated(Request $request, User $user, ?Project $existing = null): array
    {
        $input = $request->all();
        $fields = array_intersect_key($input, array_flip(['name', 'code_prefix', 'type', 'client_id', 'description', 'starts_on', 'ends_on']));
        if (array_key_exists('leader', $input)) {
            $fields['leader_user_id'] = $input['leader'] === null || $input['leader'] === '' ? null : $this->lookup->userIds($user, [$input['leader']])[0];
        }
        if (array_key_exists('members', $input)) {
            $fields['member_ids'] = $this->lookup->userIds($user, (array) $input['members']);
        }

        $validated = Validator::make($fields, ProjectRules::save($user->company_id, $existing))->validate();
        $memberIds = array_map('intval', $validated['member_ids'] ?? []);
        unset($validated['member_ids']);

        return ['data' => $validated, 'member_ids' => $memberIds];
    }

    /** @return array<string, mixed> */
    public static function schema(JsonSchema $schema, bool $creating): array
    {
        $required = fn ($field) => $creating ? $field->required() : $field;

        return [
            'name' => $required($schema->string()->description('Nombre del proyecto.')),
            'code_prefix' => $required($schema->string()->description('Prefijo de los códigos de tarea, 2 a 10 letras/números, ej. "bhn". No se puede cambiar una vez que hay tareas.')),
            'type' => $required($schema->string()->enum(array_column(ProjectType::cases(), 'value'))->description('bag = bolsa de horas vendidas; internal = interno, sin tope.')),
            'client_id' => $schema->integer()->description('Id del cliente (sale de list_clients).'),
            'description' => $schema->string()->description('Markdown.'),
            'leader' => $schema->string()->description('Email o id del responsable (queda como miembro).'),
            'starts_on' => $schema->string()->description('YYYY-MM-DD.'),
            'ends_on' => $schema->string()->description('YYYY-MM-DD.'),
            ...($creating ? ['members' => $schema->array()->items($schema->string())->description('Emails o ids de los miembros.')] : []),
        ];
    }
}
