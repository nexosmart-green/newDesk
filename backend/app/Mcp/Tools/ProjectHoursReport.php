<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project_hours_report')]
#[Description('Horas de un proyecto en un período, por persona y por tarea, con lo vendido y el % consumido. Requiere permiso projects.manage.')]
#[IsReadOnly]
class ProjectHoursReport extends NewDeskTool
{
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
        $timezone = $user->effectiveTimezone();
        $from = isset($input['from']) ? Carbon::parse($input['from'], $timezone) : Carbon::parse('2000-01-01', $timezone);
        $to = isset($input['to']) ? Carbon::parse($input['to'], $timezone) : now($timezone)->startOfDay();

        $report = $this->report->project($project, $from, $to);

        return $this->json([
            ...$report,
            'allocated' => $report['allocated_seconds'] !== null ? McpPresenter::hours($report['allocated_seconds']) : 'sin tope',
            'consumed_in_period' => McpPresenter::hours($report['consumed_in_period_seconds']),
            'by_person' => array_map(fn (array $row) => [...$row, 'time' => McpPresenter::hours($row['seconds'])], $report['by_person']),
            'by_task' => array_map(fn (array $row) => [...$row, 'time' => McpPresenter::hours($row['seconds'])], $report['by_task']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Prefijo del proyecto, ej. "bhn".')->required(),
            'from' => $schema->string()->description('YYYY-MM-DD (opcional, default: desde el inicio)'),
            'to' => $schema->string()->description('YYYY-MM-DD (opcional, default: hoy)'),
        ];
    }
}
