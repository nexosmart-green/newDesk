<?php

namespace App\Mcp\Tools;

use App\Domain\Assistant\Queries\WeeklySummaryData;
use App\Domain\Assistant\Support\WeeklySummaryTemplate;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('weekly_summary')]
#[Description('Resumen de la semana pasada (lunes a domingo) de toda la empresa: horas totales y contra la semana anterior, por proyecto y por persona, bolsas por agotarse y sin movimiento, tareas vencidas y ampliaciones pendientes. Trae los datos y un texto ya armado con esos números. Es el mismo resumen que sale los lunes. Requiere permiso projects.manage.')]
#[IsReadOnly]
class GetWeeklySummaryTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleReports;
    }

    public function __construct(private readonly WeeklySummaryData $data) {}

    protected function run(Request $request, User $user): Response
    {
        Gate::forUser($user)->authorize(Permission::ProjectsManage->value);
        $data = ($this->data)($user->company);

        return $this->json(['text' => WeeklySummaryTemplate::render($data), 'data' => $data]);
    }
}
