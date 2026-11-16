<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-09: the person chooses which events also reach them by e-mail. Locked events (Admins and the
 * hour-block e-mail) are ignored silently: the UI shows them disabled.
 */
final class UpdateNotificationPreferences
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<array{event: string, email: bool}>  $preferences */
    public function __invoke(User $user, array $preferences): void
    {
        DB::transaction(function () use ($user, $preferences) {
            $changed = [];
            foreach ($preferences as $preference) {
                $event = NotificationEvent::from($preference['event']);
                if (NotificationPreference::isLocked($user, $event)) {
                    continue;
                }

                NotificationPreference::query()->updateOrInsert(
                    ['user_id' => $user->id, 'event' => $event->value],
                    ['email' => (bool) $preference['email']],
                );
                $changed[$event->value] = (bool) $preference['email'];
            }

            if ($changed !== []) {
                ($this->recordActivity)('notification_preferences.updated', $user, $user, after: ['email' => $changed]);
            }
        });
    }
}
