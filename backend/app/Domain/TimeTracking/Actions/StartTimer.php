<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;
use App\Domain\TimeTracking\Support\UserTimerLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.02/03/05/16, R-2. Starting is atomic with the availability check: per-user lock + row lock
 * of the project(s) involved, taken in id order so two people switching between projects never deadlock.
 *
 * If another timer is running, it is closed in the same step with $previousComment (required).
 * Starting again on the task already running is a no-op (double click, second device).
 */
final class StartTimer
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly UserTimerLock $lock,
        private readonly CloseTimeEntry $closeEntry,
        private readonly ActivityContext $context,
    ) {}

    public function __invoke(User $user, Task $task, ?string $previousComment = null): TimeEntry
    {
        $this->rules->assertCanTrack($user, $task);

        return $this->lock->run($user->id, function () use ($user, $task, $previousComment) {
            $this->rules->assertNoPendingComment($user);
            $this->rules->assertNoDayClock($user);

            $active = $this->activeEntry($user);
            if ($active?->task_id === $task->id) {
                return $active;
            }
            if ($active !== null) {
                $this->rules->assertValidComment($previousComment, 'previous_comment_required');
            }

            try {
                return DB::transaction(fn () => $this->start($user, $task, $previousComment, $active?->project_id));
            } catch (UniqueConstraintViolationException) {
                // Another device won the race between our read and our insert.
                return $this->activeEntry($user) ?? throw new BusinessRuleViolation('No se pudo iniciar el cronómetro. Probá de nuevo.', 'timer_busy', 409);
            }
        });
    }

    private function start(User $user, Task $task, ?string $previousComment, ?int $previousProjectId): TimeEntry
    {
        $projects = Project::query()->forCompany($user->company_id)
            ->whereKey(array_unique(array_filter([$task->project_id, $previousProjectId])))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $task = Task::query()->forCompany($user->company_id)->with('status')->findOrFail($task->id);
        $this->rules->assertTrackable($projects[$task->project_id], $task);

        $active = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->lockForUpdate()->first();
        if ($active !== null) {
            $project = $projects[$active->project_id] ?? Project::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($active->project_id);
            ($this->closeEntry)($active, $project, now(), StopReason::Switch, $previousComment);
        }

        return TimeEntry::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'started_at' => now(),
            'source' => EntrySource::Timer,
            'channel' => $this->context->source,
            'created_by' => $user->id,
        ]);
    }

    private function activeEntry(User $user): ?TimeEntry
    {
        return TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->first();
    }
}
