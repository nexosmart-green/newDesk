<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use Illuminate\Support\Str;

/** RF-09.02 */
final class MentionedNotification extends AppNotification
{
    public function __construct(
        private readonly string $taskCode,
        private readonly string $author,
        private readonly string $excerpt,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::Mentioned;
    }

    protected function title(User $notifiable): string
    {
        return "{$this->author} te mencionó en {$this->taskCode}";
    }

    protected function body(User $notifiable): string
    {
        return '«'.Str::limit($this->excerpt, 200).'»';
    }

    protected function path(): string
    {
        return "/tareas/{$this->taskCode}";
    }
}
