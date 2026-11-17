<?php

namespace App\Domain\Assistant\Queries;

use App\Domain\Assistant\Enums\ReportKind;
use App\Domain\Assistant\Support\ReportTable;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectList;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskList;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ReportPeriod;

/**
 * RF-14.05: the rows of each file report, from THE SAME queries as the web and the MCP tools (never recomputed).
 * Authorization is the caller's (the export_report tool applies the same gates as the web).
 */
final class AssistantReportData
{
    /** Enough for any company's list; a report is a snapshot, not a paginated screen. */
    private const MAX_ROWS = 1000;

    public function __construct(
        private readonly HoursReport $hours,
        private readonly ProjectList $projects,
        private readonly TaskList $tasks,
    ) {}

    public function __invoke(ReportKind $kind, User $viewer, ReportPeriod $period): ReportTable
    {
        $range = 'Del '.$period->from->format('d/m/Y').' al '.$period->to->format('d/m/Y');
        $today = 'Al '.now()->setTimezone($viewer->effectiveTimezone())->format('d/m/Y H:i');
        [$from, $to, $companyId] = [$period->from, $period->to, $viewer->company_id];

        return match ($kind) {
            ReportKind::HoursByProject => new ReportTable('Horas por proyecto', $range,
                ['Prefijo' => ReportTable::TEXT, 'Proyecto' => ReportTable::TEXT, 'Cliente' => ReportTable::TEXT, 'Horas del período' => ReportTable::HOURS, 'Vendidas' => ReportTable::HOURS, '% consumido' => ReportTable::TEXT],
                array_map(fn (array $row) => [
                    $row['project']['prefix'], $row['project']['name'], $row['client'], $row['consumed_in_period_seconds'],
                    $row['allocated_seconds'], $row['percent_consumed'] === null ? '' : $row['percent_consumed'].' %',
                ], $this->hours->overview($companyId, $from, $to))),
            ReportKind::HoursByClient => new ReportTable('Horas por cliente', $range,
                ['Cliente' => ReportTable::TEXT, 'Proyectos' => ReportTable::TEXT, 'Horas' => ReportTable::HOURS],
                array_map(fn (array $row) => [$row['client']['name'] ?? 'Sin cliente (internos)', (string) $row['projects'], $row['seconds']],
                    $this->hours->byClient($companyId, $from, $to))),
            ReportKind::HoursByPerson => new ReportTable('Horas por persona', $range,
                ['Persona' => ReportTable::TEXT, 'Proyecto' => ReportTable::TEXT, 'Horas' => ReportTable::HOURS],
                array_merge(...array_map(fn (array $person) => array_map(
                    fn (array $project) => [$person['name'], "{$project['prefix']} — {$project['name']}", $project['seconds']],
                    $person['by_project'],
                ), $this->hours->byPerson($companyId, $from, $to)))),
            ReportKind::BagsRunningOut => new ReportTable('Bolsas por agotarse', $today,
                ['Prefijo' => ReportTable::TEXT, 'Proyecto' => ReportTable::TEXT, 'Cliente' => ReportTable::TEXT, 'Vendidas' => ReportTable::HOURS, 'Consumidas' => ReportTable::HOURS, 'Disponibles' => ReportTable::HOURS, '% consumido' => ReportTable::TEXT],
                array_map(function (Project $project) {
                    $bag = HourBag::of($project);

                    return [$project->code_prefix, $project->name, $project->client?->displayName(), $bag->allocated(), $bag->consumed(), $bag->available(), $bag->percentConsumed().' %'];
                }, ($this->projects)($viewer, ['running_out' => true], self::MAX_ROWS)->items())),
            ReportKind::OverdueTasks => new ReportTable('Tareas vencidas', $today,
                ['Código' => ReportTable::TEXT, 'Tarea' => ReportTable::TEXT, 'Proyecto' => ReportTable::TEXT, 'Asignada a' => ReportTable::TEXT, 'Vencía' => ReportTable::TEXT],
                array_map(fn (Task $task) => [
                    $task->code, $task->title, $task->project->name, $task->assignees->map->fullName()->implode(', '), $task->due_on?->format('d/m/Y'),
                ], ($this->tasks)($viewer, null, ['overdue' => true], self::MAX_ROWS)->items())),
        };
    }
}
