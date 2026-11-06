<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Actions\Recipients;
use App\Domain\Notifications\Notifications\ExtensionRequestedNotification;
use App\Domain\Notifications\Notifications\HoursThresholdNotification;
use App\Domain\Notifications\Notifications\MentionedNotification;
use App\Domain\Notifications\Notifications\TaskAssignedNotification;
use App\Domain\Notifications\Notifications\TimerCutNotification;
use App\Domain\Tasks\Events\CommentPosted;
use App\Domain\Tasks\Events\TaskAssigned;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Events\HourExtensionRequested;
use App\Domain\TimeTracking\Events\HourThresholdReached;
use App\Domain\TimeTracking\Events\TimerAutoStopped;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

/**
 * RF-09: turns domain events into notifications. The other modules don't know this one exists.
 */
final class SendDomainNotifications
{
    public function __construct(private readonly Recipients $recipients) {}

    public function onTaskAssigned(TaskAssigned $event): void
    {
        $task = $event->task->loadMissing('project');
        Notification::send($event->assignees, new TaskAssignedNotification($task->code, $task->title, $task->project->name, $event->actor->fullName()));
    }

    public function onCommentPosted(CommentPosted $event): void
    {
        $comment = $event->comment->loadMissing(['task', 'author']);
        Notification::send($event->mentioned, new MentionedNotification($comment->task->code, $comment->author->fullName(), $comment->body));
    }

    public function onHourThreshold(HourThresholdReached $event): void
    {
        Notification::send(
            $this->recipients->adminsAndLeader($event->project),
            new HoursThresholdNotification($event->project->id, $event->project->name, $event->percent),
        );
    }

    /** RF-05.06: budget cuts go to the person and to Admin; other cuts only to the person. */
    public function onTimerAutoStopped(TimerAutoStopped $event): void
    {
        $entry = $event->entry->loadMissing(['task', 'user']);
        $make = fn (bool $toOwner) => new TimerCutNotification(
            $entry->id, $entry->task->code, $entry->user->fullName(), (int) $entry->seconds, (string) $entry->stop_reason?->label(), $toOwner,
        );

        if ($entry->user->isActive()) {
            $entry->user->notify($make(true));
        }

        if ($entry->stop_reason === StopReason::BudgetExhausted) {
            $admins = $this->recipients->admins($entry->company_id)->reject(fn (User $admin) => $admin->is($entry->user));
            Notification::send($admins, $make(false));
        }
    }

    public function onExtensionRequested(HourExtensionRequested $event): void
    {
        $request = $event->request->loadMissing('project');
        Notification::send(
            $this->recipients->withPermission($request->company_id, Permission::HoursAllocate),
            new ExtensionRequestedNotification($request->project->id, $request->project->name, $event->requester->fullName(), $request->message),
        );
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TaskAssigned::class => 'onTaskAssigned',
            CommentPosted::class => 'onCommentPosted',
            HourThresholdReached::class => 'onHourThreshold',
            TimerAutoStopped::class => 'onTimerAutoStopped',
            HourExtensionRequested::class => 'onExtensionRequested',
        ];
    }
}
