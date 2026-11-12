<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\LocalDateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Project "Horas" tab (RF-05.11): the project's entries by person, task and local dates of the viewer,
 * newest first. Dates go through LocalDateRange, like "Historial de horas".
 */
final class ProjectTimeEntries
{
    public const PER_PAGE = 50;

    /**
     * @param  array{user_id?: int|null, task_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, TimeEntry>
     */
    public function paginate(Project $project, array $filters, string $timezone): LengthAwarePaginator
    {
        $query = TimeEntry::query()->forCompany($project->company_id)
            ->where('time_entries.project_id', $project->id)
            ->when($filters['user_id'] ?? null, fn (Builder $q, int $id) => $q->where('time_entries.user_id', $id))
            ->when($filters['task_id'] ?? null, fn (Builder $q, int $id) => $q->where('time_entries.task_id', $id));

        return LocalDateRange::of($filters['from'] ?? null, $filters['to'] ?? null, $timezone)->apply($query)
            ->with(['task:id,code,title,project_id', 'project:id,name,code_prefix', 'user:id,first_name,last_name'])
            ->orderByDesc('time_entries.started_at')
            ->orderByDesc('time_entries.id')
            ->paginate(self::PER_PAGE);
    }
}
