<?php

namespace App\Domain\Shared\Activity;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read side of the audit trail: the project "Actividad" tab (RF-03.06) and a task's own history
 * share the same shape — newest first, with the actor.
 */
final class ActivityFeed
{
    public const PER_PAGE = 50;

    /** Everything recorded with this project as context, whatever the subject (tasks, members, hours…). */
    public function forProject(int $projectId): LengthAwarePaginator
    {
        return $this->paginate(ActivityLog::query()->where('project_id', $projectId));
    }

    /** Only the entries whose subject is this model. */
    public function forSubject(Model $subject): LengthAwarePaginator
    {
        return $this->paginate(ActivityLog::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey()));
    }

    /** @param  Builder<ActivityLog>  $query */
    private function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->with('actor')->latest('id')->paginate(self::PER_PAGE);
    }
}
