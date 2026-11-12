<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\LocalDateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Historial de horas": one person's entries over a free range of local dates, newest first.
 * The same filter feeds the page, its total and the CSV export, so the three always agree.
 */
final class TimeEntryHistory
{
    public const PER_PAGE = 50;

    /**
     * @param  array{user_id: int, from: string, to: string, timezone: string, project_id?: int|null, search?: string|null}  $filters
     * @return Builder<TimeEntry>
     */
    public function query(array $filters): Builder
    {
        $range = LocalDateRange::of($filters['from'], $filters['to'], $filters['timezone']);

        return $range->apply(TimeEntry::query()->where('time_entries.user_id', $filters['user_id']))
            ->when($filters['project_id'] ?? null, fn (Builder $q, int $id) => $q->where('time_entries.project_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $inner) => $inner
                ->where('time_entries.comment', 'like', "%{$search}%")
                ->orWhereHas('task', fn (Builder $task) => $task->where(fn (Builder $match) => $match
                    ->where('tasks.code', 'like', "%{$search}%")
                    ->orWhere('tasks.title', 'like', "%{$search}%")))));
    }

    /**
     * @param  array{user_id: int, from: string, to: string, timezone: string, project_id?: int|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, TimeEntry>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->newestFirst($this->query($filters))->paginate(self::PER_PAGE);
    }

    /** Whole filter, not just the page; running timers count up to now. */
    public function totalSeconds(array $filters): int
    {
        return (int) $this->query($filters)->sum(TimeEntry::elapsedSecondsExpression());
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @return Builder<TimeEntry>
     */
    public function newestFirst(Builder $query): Builder
    {
        return $query
            ->with(['user:id,first_name,last_name,avatar_path', 'task:id,code,title', 'project:id,name'])
            ->orderByDesc('time_entries.started_at')
            ->orderByDesc('time_entries.id');
    }
}
