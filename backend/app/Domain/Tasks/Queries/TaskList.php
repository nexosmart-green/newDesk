<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\StatusKind;
use App\Domain\Tasks\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lists and board share this query (RF-04.04). Filters per RF-04.05; "Mis tareas" (RF-04.07) is
 * the same query scoped to the viewer's assignments, and "Todas las tareas" without that scope —
 * both across the projects the viewer can see.
 */
final class TaskList
{
    /**
     * @param  array{status_ids?: list<int>, include_closed?: bool, assignee_id?: int|null, overdue?: bool, search?: string|null, project_id?: int|null, category_id?: int|null, label_id?: int|null, mine?: bool}  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function __invoke(User $viewer, ?Project $project, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = Task::query()
            ->withLoggedSeconds()
            ->with(['status', 'assignees', 'category', 'labels', 'project:id,company_id,name,code_prefix,status,type,allocated_seconds,consumed_seconds'])
            ->when($project, fn (Builder $q) => $q->where('tasks.project_id', $project->id))
            ->when(! $project, fn (Builder $q) => $q->visibleTo($viewer))
            ->when($filters['project_id'] ?? null, fn (Builder $q, int $id) => $q->where('tasks.project_id', $id))
            ->when($filters['mine'] ?? false, fn (Builder $q) => $q->whereHas('assignees', fn (Builder $a) => $a->whereKey($viewer->id)))
            ->when($filters['assignee_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('assignees', fn (Builder $a) => $a->whereKey($id)))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->where('tasks.category_id', $id))
            ->when($filters['label_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('labels', fn (Builder $l) => $l->whereKey($id)))
            ->when($filters['status_ids'] ?? [], fn (Builder $q, array $ids) => $q->whereIn('status_id', $ids))
            ->when(! ($filters['include_closed'] ?? false) && ($filters['status_ids'] ?? []) === [], fn (Builder $q) => $q
                ->whereHas('status', fn (Builder $s) => $s->where('kind', '!=', StatusKind::Closed)))
            ->when($filters['overdue'] ?? false, fn (Builder $q) => $q->open()->where('due_on', '<', $viewer->localDate()))
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $inner) => $inner
                ->where('tasks.code', $search)
                ->orWhere('tasks.title', 'like', "%{$search}%")));

        // Overdue first, then by due date (no date last), then newest.
        return $query
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->orderByDesc('tasks.id')
            ->paginate($perPage);
    }
}
