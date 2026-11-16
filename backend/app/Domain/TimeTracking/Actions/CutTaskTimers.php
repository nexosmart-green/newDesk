<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Support\TimerCutter;
use Illuminate\Database\Eloquent\Builder;

/** CA-04.03: closing a task stops every timer running on it. */
final class CutTaskTimers
{
    public function __construct(private readonly TimerCutter $cutter) {}

    /** @return int entries cut */
    public function __invoke(Task $task): int
    {
        return $this->cutter->cut($task->company_id, $task->project_id, StopReason::TaskClosed, fn (Builder $q) => $q->where('task_id', $task->id));
    }
}
