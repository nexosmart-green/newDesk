<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;

/** RF-09.06 / RF-05.08 */
final class ExtensionRequestedNotification extends AppNotification
{
    public function __construct(
        private readonly int $projectId,
        private readonly string $projectName,
        private readonly string $requester,
        private readonly ?string $message,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::ExtensionRequested;
    }

    protected function title(User $notifiable): string
    {
        return "{$this->requester} pide más horas para {$this->projectName}";
    }

    protected function body(User $notifiable): string
    {
        return $this->message ? "«{$this->message}»" : 'Sin mensaje.';
    }

    protected function path(): string
    {
        return "/proyectos/{$this->projectId}/horas";
    }
}
