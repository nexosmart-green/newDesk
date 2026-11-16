<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Task "Horas" tab: every entry of the task, by anyone, newest first. */
final class TaskTimeEntries
{
    public const PER_PAGE = 50;

    /** @return LengthAwarePaginator<int, TimeEntry> */
    public function paginate(Task $task): LengthAwarePaginator
    {
        return TimeEntry::query()
            ->where('task_id', $task->id)
            ->with('user:id,first_name,last_name,avatar_path')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }
}
