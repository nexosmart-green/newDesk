<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Support\Duration;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Domain\TimeTracking\Support\ReportPeriod;
use App\Mcp\Support\McpLookup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project_hours_report')]
#[Description('Horas de un proyecto en un período, por persona y por tarea, con lo vendido y el % consumido. Requiere permiso projects.manage.')]
#[IsReadOnly]
class GetProjectHoursReportTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleReports;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly HoursReport $report) {}

    protected function run(Request $request, User $user): Response
    {
        Gate::forUser($user)->authorize(Permission::ProjectsManage->value);
        $input = $request->validate([
            'project' => 'required|string',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $project = $this->lookup->project($user, $input['project']);
        $period = ReportPeriod::fromInput($user, $input['from'] ?? null, $input['to'] ?? null);

        $report = $this->report->project($project, $period->from, $period->to);

        return $this->json([
            ...$report,
            'allocated' => $report['allocated_seconds'] !== null ? Duration::humanize($report['allocated_seconds']) : 'sin tope',
            'consumed_in_period' => Duration::humanize($report['consumed_in_period_seconds']),
            'by_person' => array_map(fn (array $row) => [...$row, 'time' => Duration::humanize($row['seconds'])], $report['by_person']),
            'by_task' => array_map(fn (array $row) => [...$row, 'time' => Duration::humanize($row['seconds'])], $report['by_task']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'from' => $schema->string()->description('YYYY-MM-DD (opcional, default: el 1 del mes actual)'),
            'to' => $schema->string()->description('YYYY-MM-DD (opcional, default: hoy)'),
        ];
    }
}
