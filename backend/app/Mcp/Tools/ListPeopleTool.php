<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\People\Models\Area;
use App\Domain\People\Models\CatalogItem;
use App\Domain\People\Models\Skill;
use App\Domain\People\Queries\PeopleDirectory;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Mcp\Support\McpManagementPresenter;
use App\Mcp\Support\McpPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_people')]
#[Description('Directorio de personas activas de la empresa: nombre, email, puesto y área. Filtros: texto (nombre o email), área, habilidad ("¿quién sabe React?"). Los emails sirven para asignar tareas o sumar miembros. No incluye tarifas ni datos personales.')]
#[IsReadOnly]
class ListPeopleTool extends NewDeskTool
{
    public function __construct(private readonly PeopleDirectory $directory) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'search' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:100',
            'skill' => 'nullable|string|max:100',
            ...McpPage::rules(),
        ]);

        $people = ($this->directory)($user->company_id, [
            'search' => $input['search'] ?? null,
            'area_id' => isset($input['area']) ? $this->catalogId($user, Area::class, $input['area'], 'No hay un área') : null,
            'skill_id' => isset($input['skill']) ? $this->catalogId($user, Skill::class, $input['skill'], 'No hay una habilidad') : null,
        ], McpPage::limit($request), McpPage::page($request));

        return $this->json(McpPage::of($people, 'people', fn (User $person) => McpManagementPresenter::person($person)));
    }

    /** @param  class-string<CatalogItem>  $catalog */
    private function catalogId(User $user, string $catalog, string $name, string $notFound): int
    {
        $items = $catalog::query()->forCompany($user->company_id)->orderBy('name')->get(['id', 'name']);

        return $items->first(fn (CatalogItem $item) => mb_strtolower($item->name) === mb_strtolower(trim($name)))?->id
            ?? throw new BusinessRuleViolation("{$notFound} «{$name}». Usá una de estas: ".$items->pluck('name')->implode(', ').'.', 'catalog_unknown');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Parte del nombre o del email.'),
            'area' => $schema->string()->description('Nombre exacto del área.'),
            'skill' => $schema->string()->description('Nombre exacto de la habilidad, ej. "React".'),
            ...McpPage::schema($schema),
        ];
    }
}
