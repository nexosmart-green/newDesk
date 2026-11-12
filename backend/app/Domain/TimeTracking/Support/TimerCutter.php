<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\CloseTimeEntry;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Events\TimerAutoStopped;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one way the system stops timers nobody chose to stop (the Cut* actions). Entries end `comment_pending`
 * and the person is notified. Running entries of one project are closed under its row lock, oldest first,
 * so the bag is shared out exactly (R-1).
 */
final class TimerCutter
{
    public function __construct(private readonly CloseTimeEntry $closeEntry) {}

    /**
     * @param  (callable(Builder): mixed)|null  $constrain  narrows the project's running entries (one task, one person…)
     * @return int entries cut
     */
    public function cut(int $companyId, int $projectId, StopReason $reason, ?callable $constrain = null, ?Carbon $endAt = null): int
    {
        $stopped = DB::transaction(function () use ($companyId, $projectId, $reason, $constrain, $endAt) {
            $project = Project::query()->forCompany($companyId)->lockForUpdate()->findOrFail($projectId);

            // The exhaustion check outside the lock may be stale (an extension may have landed): re-check (R-3).
            if ($reason === StopReason::BudgetExhausted && ! HourBag::of($project, $endAt ?? now())->isExhausted()) {
                return [];
            }

            $entries = TimeEntry::query()->forCompany($companyId)
                ->where('project_id', $projectId)->running()
                ->when($constrain, $constrain)
                ->orderBy('started_at')->orderBy('id')
                ->lockForUpdate()->get();

            return $entries->map(fn (TimeEntry $entry) => ($this->closeEntry)($entry, $project, $endAt ?? now(), $reason))->all();
        });

        foreach ($stopped as $entry) {
            TimerAutoStopped::dispatch($entry);
        }

        return count($stopped);
    }
}
