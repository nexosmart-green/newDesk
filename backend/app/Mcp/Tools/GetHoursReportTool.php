<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Support\Duration;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Domain\TimeTracking\Support\ReportPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('hours_report')]
#[Description('Horas de TODA la empresa en un período, agrupadas por proyecto (con lo vendido y el % consumido de cada bolsa), por cliente o por persona (con su reparto por proyecto). Solo registros cerrados. Para el detalle por tarea de un proyecto usá project_hours_report. Requiere permiso projects.manage.')]
#[IsReadOnly]
class GetHoursReportTool extends NewDeskTool
{
    private const GROUPS = ['project', 'client', 'person'];

    protected function module(): ?Permission
    {
        return Permission::ModuleReports;
    }

    public function __construct(private readonly HoursReport $report) {}

    protected function run(Request $request, User $user): Response
    {
        Gate::forUser($user)->authorize(Permission::ProjectsManage->value);
        $input = $request->validate([
            'group_by' => 'nullable|in:'.implode(',', self::GROUPS),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $period = ReportPeriod::fromInput($user, $input['from'] ?? null, $input['to'] ?? null);
        [$from, $to, $companyId] = [$period->from, $period->to, $user->company_id];

        $rows = match ($input['group_by'] ?? 'project') {
            'client' => $this->report->byClient($companyId, $from, $to),
            'person' => array_map(fn (array $row) => [
                ...$row,
                'by_project' => array_map(fn (array $project) => [...$project, 'time' => Duration::humanize($project['seconds'])], $row['by_project']),
            ], $this->report->byPerson($companyId, $from, $to)),
            default => array_map(fn (array $row) => [
                ...$row,
                'allocated' => $row['allocated_seconds'] !== null ? Duration::humanize($row['allocated_seconds']) : 'sin tope',
                'seconds' => $row['consumed_in_period_seconds'],
            ], $this->report->overview($companyId, $from, $to)),
        };
        $rows = array_map(fn (array $row) => [...$row, 'time' => Duration::humanize($row['seconds'])], $rows);
        $total = array_sum(array_column($rows, 'seconds'));

        return $this->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'group_by' => $input['group_by'] ?? 'project',
            'total' => Duration::humanize($total),
            'total_seconds' => $total,
            'rows' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'group_by' => $schema->string()->enum(self::GROUPS)->description('Cómo agrupar (default project).'),
            'from' => $schema->string()->description('YYYY-MM-DD (opcional, default: el 1 del mes actual)'),
            'to' => $schema->string()->description('YYYY-MM-DD (opcional, default: hoy)'),
        ];
    }
}
