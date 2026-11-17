<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-10.01 (R1): hours per project and period — sold / consumed / % / per person — plus per task for the
 * project detail, and the period's hours per client and per person (RF-14.07). Closed entries only (a running timer is not "consumed in the period" until it stops).
 * Dates are local to the company; filtering is by range on started_at (index-friendly).
 */
final class HoursReport
{
    /** @return list<array<string, mixed>> */
    public function overview(int $companyId, Carbon $from, Carbon $to): array
    {
        $consumed = $this->entries($companyId, $from, $to)
            ->groupBy('project_id')
            ->pluck(DB::raw('SUM(seconds)'), 'project_id');

        return Project::query()->forCompany($companyId)
            ->whereIn('id', $consumed->keys())
            ->orWhere(fn ($query) => $query->where('company_id', $companyId)->where('status', ProjectStatus::Active))
            ->with('client')->orderBy('name')->get()
            ->map(fn (Project $project) => [
                'project' => ['id' => $project->id, 'name' => $project->name, 'prefix' => $project->code_prefix],
                'client' => $project->client?->displayName(),
                'type' => $project->type->value,
                'allocated_seconds' => $project->type->hasHourCap() ? $project->allocated_seconds : null,
                'consumed_total_seconds' => $project->consumed_seconds,
                'consumed_in_period_seconds' => (int) ($consumed[$project->id] ?? 0),
                'percent_consumed' => $this->percent($project),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    public function project(Project $project, Carbon $from, Carbon $to): array
    {
        $base = fn () => $this->entries($project->company_id, $from, $to)->where('time_entries.project_id', $project->id);

        $byPerson = $base()->join('users', 'users.id', '=', 'time_entries.user_id')
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderByRaw('SUM(seconds) DESC')
            ->get(['users.id', 'users.first_name', 'users.last_name', DB::raw('SUM(seconds) as seconds')])
            ->map(fn ($row) => ['user_id' => $row->id, 'name' => trim("{$row->first_name} {$row->last_name}"), 'seconds' => (int) $row->seconds])
            ->all();

        $byTask = $base()->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->groupBy('tasks.id', 'tasks.code', 'tasks.title')
            ->orderByRaw('SUM(seconds) DESC')
            ->get(['tasks.code', 'tasks.title', DB::raw('SUM(seconds) as seconds')])
            ->map(fn ($row) => ['code' => $row->code, 'title' => $row->title, 'seconds' => (int) $row->seconds])
            ->all();

        return [
            'project' => ['id' => $project->id, 'name' => $project->name, 'prefix' => $project->code_prefix],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'allocated_seconds' => $project->type->hasHourCap() ? $project->allocated_seconds : null,
            'consumed_total_seconds' => $project->consumed_seconds,
            'consumed_in_period_seconds' => array_sum(array_column($byPerson, 'seconds')),
            'percent_consumed' => $this->percent($project),
            'by_person' => $byPerson,
            'by_task' => $byTask,
        ];
    }

    /**
     * Hours of the period per client (projects without a client are grouped under client null), most hours first.
     *
     * @return list<array{client: array{id: int, name: string}|null, seconds: int, projects: int}>
     */
    public function byClient(int $companyId, Carbon $from, Carbon $to): array
    {
        $rows = $this->entries($companyId, $from, $to)
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->whereNull('projects.deleted_at') // same projects as overview(), which goes through the model
            ->groupBy('projects.client_id')
            ->orderByRaw('SUM(time_entries.seconds) DESC')
            ->get(['projects.client_id', DB::raw('SUM(time_entries.seconds) as seconds'), DB::raw('COUNT(DISTINCT projects.id) as projects')]);
        $clients = Client::query()->forCompany($companyId)->whereKey($rows->pluck('client_id')->filter())->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'client' => $row->client_id === null ? null : ['id' => (int) $row->client_id, 'name' => $clients[$row->client_id]->displayName()],
            'seconds' => (int) $row->seconds,
            'projects' => (int) $row->projects,
        ])->values()->all();
    }

    /**
     * Hours of the period per person across every project, with their split by project; most hours first.
     *
     * @return list<array{user_id: int, name: string, seconds: int, by_project: list<array{prefix: string, name: string, seconds: int}>}>
     */
    public function byPerson(int $companyId, Carbon $from, Carbon $to): array
    {
        $rows = $this->entries($companyId, $from, $to)
            ->join('users', 'users.id', '=', 'time_entries.user_id')
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->whereNull('projects.deleted_at')
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'projects.id', 'projects.code_prefix', 'projects.name')
            ->orderByRaw('SUM(time_entries.seconds) DESC')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'projects.code_prefix', 'projects.name', DB::raw('SUM(time_entries.seconds) as seconds')]);

        return $rows->groupBy('id')
            ->map(fn ($projects) => [
                'user_id' => (int) $projects->first()->id,
                'name' => trim("{$projects->first()->first_name} {$projects->first()->last_name}"),
                'seconds' => (int) $projects->sum('seconds'),
                'by_project' => $projects->map(fn ($row) => ['prefix' => $row->code_prefix, 'name' => $row->name, 'seconds' => (int) $row->seconds])->values()->all(),
            ])
            ->sortByDesc('seconds')->values()->all();
    }

    private function entries(int $companyId, Carbon $from, Carbon $to): Builder
    {
        return DB::table('time_entries')
            ->where('time_entries.company_id', $companyId)
            ->whereNull('time_entries.deleted_at')
            ->whereNotNull('time_entries.ended_at')
            ->where('time_entries.started_at', '>=', $from->copy()->utc())
            ->where('time_entries.started_at', '<', $to->copy()->addDay()->startOfDay()->utc());
    }

    private function percent(Project $project): ?int
    {
        if (! $project->type->hasHourCap() || $project->allocated_seconds <= 0) {
            return null;
        }

        return (int) floor($project->consumed_seconds * 100 / $project->allocated_seconds);
    }
}
