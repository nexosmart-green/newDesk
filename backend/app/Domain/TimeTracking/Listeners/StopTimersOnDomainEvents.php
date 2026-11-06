<?php

namespace App\Domain\TimeTracking\Listeners;

use App\Domain\People\Events\UserDeactivated;
use App\Domain\Projects\Events\ProjectClosed;
use App\Domain\Projects\Events\ProjectMemberRemoved;
use App\Domain\Tasks\Events\TaskClosed;
use App\Domain\TimeTracking\Actions\AutoStopTimers;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Events\Dispatcher;

/**
 * TimeTracking reacts to other modules without them knowing it (they only raise events).
 * Synchronous on purpose: the cut happens inside the same transaction as the cause.
 */
final class StopTimersOnDomainEvents
{
    public function __construct(private readonly AutoStopTimers $autoStop) {}

    /** CA-04.03 */
    public function onTaskClosed(TaskClosed $event): void
    {
        $this->autoStop->forTask($event->task);
    }

    /** CA-03.08 */
    public function onProjectClosed(ProjectClosed $event): void
    {
        $this->autoStop->forProject($event->project);
    }

    /** CA-01.10 */
    public function onUserDeactivated(UserDeactivated $event): void
    {
        $this->autoStop->forUser($event->user);
    }

    /** Someone leaving a project cannot keep a timer on it. */
    public function onMemberRemoved(ProjectMemberRemoved $event): void
    {
        $running = TimeEntry::query()->forCompany($event->project->company_id)
            ->where('user_id', $event->member->id)->where('project_id', $event->project->id)->running()->exists();

        if ($running) {
            $this->autoStop->forUser($event->member);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TaskClosed::class => 'onTaskClosed',
            ProjectClosed::class => 'onProjectClosed',
            UserDeactivated::class => 'onUserDeactivated',
            ProjectMemberRemoved::class => 'onMemberRemoved',
        ];
    }
}
