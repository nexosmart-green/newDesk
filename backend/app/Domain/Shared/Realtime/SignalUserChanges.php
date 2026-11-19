<?php

namespace App\Domain\Shared\Realtime;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * RNF-12: one place that turns "a person's things changed" into a {@see UserSignal}, whoever changed them
 * (web, MCP, the assistant, the minute job). Mass `update()`s skip model events: those signal on their own.
 */
final class SignalUserChanges
{
    public function timerChanged(TimeEntry|DayClock $model): void
    {
        UserSignal::send($model->user_id, UserSignal::TIMER);
    }

    public function notificationSent(NotificationSent $event): void
    {
        if ($event->channel === 'database' && $event->notifiable instanceof User) {
            UserSignal::send($event->notifiable->id, UserSignal::NOTIFICATIONS);
        }
    }

    /** @return array<string, string> */
    public function subscribe(Dispatcher $events): array
    {
        $timer = [];
        foreach ([TimeEntry::class, DayClock::class] as $model) {
            foreach (['saved', 'deleted', 'restored'] as $change) {
                $timer["eloquent.{$change}: {$model}"] = 'timerChanged';
            }
        }

        return [...$timer, NotificationSent::class => 'notificationSent'];
    }
}
