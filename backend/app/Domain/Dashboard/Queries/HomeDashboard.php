<?php

namespace App\Domain\Dashboard\Queries;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Queries\Timesheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Inicio" panel. Each block exists only if the viewer's role reaches it (module or permission);
 * otherwise it is null so the UI simply does not draw it. Dates are the viewer's local ones.
 */
final class HomeDashboard
{
    public const DUE_SOON_LIMIT = 5;

    public const BAGS_LIMIT = 5;

    /** "Por agotarse": same first alert threshold as RF-05.07. */
    public const BAG_ALERT_PERCENT = 80;

    public function __construct(private readonly Timesheet $timesheet) {}

    /**
     * @return array{
     *     tasks: array{open: int, overdue: int, due_today: int, due_soon: Collection<int, Task>}|null,
     *     projects: array{active: int}|null,
     *     week: array{from: string, to: string, total_seconds: int, days: list<array{date: string, seconds: int}>}|null,
     *     bags_running_out: list<array{id: int, name: string, code_prefix: string, allocated_seconds: int, consumed_seconds: int}>|null,
     * }
     */
    public function __invoke(User $viewer): array
    {
        $today = now($viewer->effectiveTimezone())->startOfDay();
        $seesProjects = $viewer->hasPermission(Permission::ModuleProjects);

        return [
            'tasks' => $seesProjects ? $this->myTasks($viewer, $today) : null,
            'projects' => $seesProjects ? ['active' => $this->activeProjects($viewer)->count()] : null,
            'week' => $viewer->hasPermission(Permission::ModuleTime) ? $this->week($viewer, $today) : null,
            'bags_running_out' => $viewer->hasPermission(Permission::HoursAllocate) || $viewer->hasPermission(Permission::ProjectsManage)
                ? $this->bagsRunningOut($viewer)
                : null,
        ];
    }

    /** @return array{open: int, overdue: int, due_today: int, due_soon: Collection<int, Task>} */
    private function myTasks(User $viewer, Carbon $today): array
    {
        $mine = fn (): Builder => Task::query()->visibleTo($viewer)->open()
            ->whereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($viewer->id));
        $date = $today->toDateString();

        return [
            'open' => $mine()->count(),
            'overdue' => $mine()->where('tasks.due_on', '<', $date)->count(),
            'due_today' => $mine()->where('tasks.due_on', $date)->count(),
            'due_soon' => $mine()->where('tasks.due_on', '>=', $date)
                ->withLoggedSeconds()
                ->withBadgeCounts()
                ->with(Task::RESOURCE_RELATIONS)
                ->orderBy('tasks.due_on')
                ->orderBy('tasks.id')
                ->limit(self::DUE_SOON_LIMIT)
                ->get(),
        ];
    }

    /** @return Builder<Project> */
    private function activeProjects(User $viewer): Builder
    {
        return Project::query()
            ->visibleTo($viewer, ProjectPolicy::seesAllProjects($viewer))
            ->where('projects.status', ProjectStatus::Active);
    }

    /** @return array{from: string, to: string, total_seconds: int, days: list<array{date: string, seconds: int}>} */
    private function week(User $viewer, Carbon $today): array
    {
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->addDays(6);
        $sheet = ($this->timesheet)($viewer, $monday->toDateString(), $sunday->toDateString());

        return [
            'from' => $monday->toDateString(),
            'to' => $sunday->toDateString(),
            'total_seconds' => $sheet['total_seconds'],
            'days' => array_map(fn (array $day) => ['date' => $day['date'], 'seconds' => $day['total_seconds']], $sheet['days']),
        ];
    }

    /**
     * Active bag projects at or past the alert threshold, most consumed first. Running timers count
     * (same "Consumidas" as HourBag) and come in the same query.
     *
     * @return list<array{id: int, name: string, code_prefix: string, allocated_seconds: int, consumed_seconds: int}>
     */
    private function bagsRunningOut(User $viewer): array
    {
        $running = DB::table('time_entries')
            ->whereColumn('time_entries.project_id', 'projects.id')
            ->whereNull('time_entries.ended_at')
            ->whereNull('time_entries.deleted_at')
            ->selectRaw('COALESCE(SUM('.TimeEntry::elapsedSecondsExpression()->getValue(DB::connection()->getQueryGrammar()).'), 0)');

        return $this->activeProjects($viewer)
            ->where('projects.type', ProjectType::Bag)
            ->select('projects.*')
            ->selectSub($running, 'running_seconds')
            ->get()
            ->map(function (Project $project) {
                $consumed = $project->consumed_seconds + (int) $project->getAttribute('running_seconds');
                $allocated = $project->allocated_seconds;
                $percent = $allocated > 0 ? $consumed * 100 / $allocated : ($consumed > 0 ? 100 : 0);

                return ['project' => $project, 'consumed' => $consumed, 'percent' => $percent];
            })
            ->filter(fn (array $row) => $row['percent'] >= self::BAG_ALERT_PERCENT)
            ->sortByDesc('percent')
            ->take(self::BAGS_LIMIT)
            ->map(fn (array $row) => [
                'id' => $row['project']->id,
                'name' => $row['project']->name,
                'code_prefix' => $row['project']->code_prefix,
                'allocated_seconds' => $row['project']->allocated_seconds,
                'consumed_seconds' => $row['consumed'],
            ])
            ->values()
            ->all();
    }
}
