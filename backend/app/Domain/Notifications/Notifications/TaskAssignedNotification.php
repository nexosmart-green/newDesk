<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;

/** RF-09.01 */
final class TaskAssignedNotification extends AppNotification
{
    public function __construct(
        private readonly string $taskCode,
        private readonly string $taskTitle,
        private readonly string $projectName,
        private readonly string $assignedBy,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::TaskAssigned;
    }

    protected function title(User $notifiable): string
    {
        return "Te asignaron {$this->taskCode}";
    }

    protected function body(User $notifiable): string
    {
        return "{$this->assignedBy} te asignó «{$this->taskTitle}» en {$this->projectName}.";
    }

    protected function path(): string
    {
        return "/tareas/{$this->taskCode}";
    }
}
