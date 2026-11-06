<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF-03.07 project list. "Con horas por agotarse" and % consumed join in with the hour bag (E4).
 */
final class ProjectList
{
    /**
     * @param  array{client_id?: int|null, status?: string|null, search?: string|null, running_out?: bool}  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function __invoke(User $viewer, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Project::query()
            ->visibleTo($viewer, ProjectPolicy::seesAllProjects($viewer))
            ->with(['client', 'leader'])
            ->withCount('members')
            ->when($filters['client_id'] ?? null, fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            // "Con horas por agotarse" (RF-03.07): bag projects at or past the first alert threshold.
            ->when($filters['running_out'] ?? false, fn (Builder $query) => $query
                ->where('type', 'bag')
                ->where('hour_alert_level', '>', 0))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code_prefix', 'like', "{$search}%")))
            ->orderBy('name')
            ->paginate($perPage);
    }
}
