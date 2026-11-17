<?php

namespace App\Domain\Assistant\Queries;

use App\Domain\Companies\Models\Company;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Domain\TimeTracking\Queries\PendingExtensionRequests;
use Illuminate\Support\Carbon;

/**
 * RF-14.06: the numbers of the weekly summary — computed by the system, never by the AI. The week is the last
 * full one (Monday to Sunday) in the company's time zone; hours come from the SAME query as the web hours
 * report, so the summary matches it for that range (CA-14.06). Company-wide: it has no viewer.
 *
 * @phpstan-type WeeklySummary array{
 *   company: string,
 *   week: array{from: string, to: string},
 *   total_seconds: int,
 *   previous_total_seconds: int,
 *   by_project: list<array{prefix: string, name: string, client: string|null, seconds: int}>,
 *   by_person: list<array{name: string, seconds: int}>,
 *   bags_running_out: list<array{prefix: string, name: string, allocated_seconds: int, consumed_seconds: int, percent: int}>,
 *   idle_bags: list<array{prefix: string, name: string}>,
 *   overdue: array{count: int, oldest: list<array{code: string, title: string, project: string, due_on: string, assignees: list<string>}>},
 *   pending_extensions: list<array{id: int, project: string, requested_by: string}>,
 * }
 */
final class WeeklySummaryData
{
    private const OLDEST_OVERDUE = 5;

    public function __construct(private readonly HoursReport $hours, private readonly PendingExtensionRequests $pendingExtensions) {}

    /** The last full week before `$now`, as the company's local Monday and Sunday. @return array{0: Carbon, 1: Carbon} */
    public static function lastWeek(Company $company, ?Carbon $now = null): array
    {
        $monday = ($now ?? now())->copy()->setTimezone($company->timezone)->startOfWeek(Carbon::MONDAY)->subWeek();

        return [$monday, $monday->copy()->addDays(6)];
    }

    /** @return WeeklySummary */
    public function __invoke(Company $company, ?Carbon $now = null): array
    {
        [$from, $to] = self::lastWeek($company, $now);
        $projects = $this->hours->overview($company->id, $from, $to);
        $previous = $this->hours->overview($company->id, $from->copy()->subWeek(), $to->copy()->subWeek());
        $today = ($now ?? now())->copy()->setTimezone($company->timezone)->toDateString();

        return [
            'company' => $company->name,
            'week' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_seconds' => array_sum(array_column($projects, 'consumed_in_period_seconds')),
            'previous_total_seconds' => array_sum(array_column($previous, 'consumed_in_period_seconds')),
            'by_project' => collect($projects)->where('consumed_in_period_seconds', '>', 0)->sortByDesc('consumed_in_period_seconds')
                ->map(fn (array $row) => [
                    'prefix' => $row['project']['prefix'], 'name' => $row['project']['name'], 'client' => $row['client'],
                    'seconds' => $row['consumed_in_period_seconds'],
                ])->values()->all(),
            'by_person' => array_map(fn (array $row) => ['name' => $row['name'], 'seconds' => $row['seconds']],
                $this->hours->byPerson($company->id, $from, $to)),
            'bags_running_out' => $this->bagsRunningOut($company),
            'idle_bags' => collect($projects)->where('type', 'bag')->where('consumed_in_period_seconds', 0)
                ->map(fn (array $row) => ['prefix' => $row['project']['prefix'], 'name' => $row['project']['name']])->values()->all(),
            'overdue' => $this->overdue($company, $today),
            'pending_extensions' => ($this->pendingExtensions)($company->id)
                ->map(fn (HourExtensionRequest $request) => [
                    'id' => $request->id, 'project' => $request->project->code_prefix, 'requested_by' => $request->requester->fullName(),
                ])->values()->all(),
        ];
    }

    /** Same definition as Inicio and the project list (Project::scopeRunningOut), active bags only. */
    private function bagsRunningOut(Company $company): array
    {
        return Project::query()->forCompany($company->id)->where('projects.status', ProjectStatus::Active)
            ->runningOut($company->firstHourAlertThreshold())->withRunningSeconds()->mostConsumedFirst()->get()
            ->map(fn (Project $project) => [
                'prefix' => $project->code_prefix,
                'name' => $project->name,
                'allocated_seconds' => $project->allocated_seconds,
                'consumed_seconds' => $project->consumedWithRunningSeconds(),
                'percent' => (int) floor($project->consumedWithRunningSeconds() * 100 / max(1, $project->allocated_seconds)),
            ])->values()->all();
    }

    /** Open tasks past their due date (company's today), the oldest first. */
    private function overdue(Company $company, string $today): array
    {
        // Tasks of a deleted project are out (as in the web lists).
        $query = Task::query()->forCompany($company->id)->open()->where('due_on', '<', $today)->whereHas('project');

        return [
            'count' => (clone $query)->count(),
            'oldest' => (clone $query)->with(['project', 'assignees'])->orderBy('due_on')->limit(self::OLDEST_OVERDUE)->get()
                ->map(fn (Task $task) => [
                    'code' => $task->code, 'title' => $task->title, 'project' => $task->project->code_prefix,
                    'due_on' => $task->due_on->toDateString(), 'assignees' => $task->assignees->map->fullName()->values()->all(),
                ])->values()->all(),
        ];
    }
}
