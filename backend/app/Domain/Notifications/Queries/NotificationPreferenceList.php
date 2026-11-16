<?php

namespace App\Domain\Notifications\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Notifications\Models\NotificationPreference;

/**
 * RF-09: one row per event with the person's e-mail choice. `locked` = Admins cannot mute it.
 */
final class NotificationPreferenceList
{
    /** @return list<array{event: string, label: string, email: bool, locked: bool}> */
    public function __invoke(User $user): array
    {
        return array_map(fn (NotificationEvent $event) => [
            'event' => $event->value,
            'label' => $event->label(),
            'email' => NotificationPreference::wantsEmail($user, $event),
            'locked' => NotificationPreference::isLocked($user, $event),
        ], NotificationEvent::cases());
    }
}
