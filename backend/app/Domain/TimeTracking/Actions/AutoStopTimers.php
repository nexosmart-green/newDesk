<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Events\TimerAutoStopped;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\ForgottenTimerDeadline;
use App\Domain\TimeTracking\Support\HourBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every cut nobody chose (RF-05.06/10/17, CA-01.10, CA-03.08, CA-04.03). Entries end `comment_pending`
 * and the person is notified. Running entries of one project are closed under its row lock, oldest first,
 * so the bag is shared out exactly (R-1).
 */
final class AutoStopTimers
{
    public function __construct(
        private readonly CloseTimeEntry $closeEntry,
        private readonly ForgottenTimerDeadline $forgottenDeadline,
    ) {}

    public function forTask(Task $task): int
    {
        return $this->cut($task->company_id, $task->project_id, StopReason::TaskClosed, fn (Builder $q) => $q->where('task_id', $task->id));
    }

    public function forProject(Project $project): int
    {
        return $this->cut($project->company_id, $project->id, StopReason::ProjectClosed);
    }

    public function forUser(User $user): int
    {
        $entry = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->first();

        return $entry === null ? 0
            : $this->cut($user->company_id, $entry->project_id, StopReason::UserDeactivated, fn (Builder $q) => $q->where('user_id', $user->id));
    }

    /** Minute job (RF-05.06): bag projects whose running timers used up what was left. */
    public function exhaustedBudgets(?Carbon $now = null): int
    {
        $now ??= now();
        $cut = 0;

        $projects = Project::query()->withoutGlobalScopes()
            ->where('type', 'bag')
            ->whereIn('id', TimeEntry::query()->withoutGlobalScopes()->running()->select('project_id'))
            ->get();

        foreach ($projects as $project) {
            if (HourBag::of($project, $now)->isExhausted()) {
                $cut += $this->cut($project->company_id, $project->id, StopReason::BudgetExhausted, endAt: $now);
            }
        }

        return $cut;
    }

    /** Minute job (RF-05.10, P-07): timers running past their deadline end AT the deadline. */
    public function forgotten(?Carbon $now = null): int
    {
        $now ??= now();
        $cut = 0;

        $running = TimeEntry::query()->withoutGlobalScopes()->running()->with('user.company')->get();
        foreach ($running as $entry) {
            $deadline = $this->forgottenDeadline->for($entry);
            if ($deadline->lessThanOrEqualTo($now)) {
                $cut += $this->cut($entry->company_id, $entry->project_id, StopReason::Forgotten,
                    fn (Builder $q) => $q->whereKey($entry->id), $deadline);
            }
        }

        return $cut;
    }

    /** @param  (callable(Builder): mixed)|null  $constrain */
    private function cut(int $companyId, int $projectId, StopReason $reason, ?callable $constrain = null, ?Carbon $endAt = null): int
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
