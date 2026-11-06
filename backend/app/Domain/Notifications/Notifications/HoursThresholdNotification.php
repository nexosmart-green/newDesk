<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;

/** RF-09.04 / RF-05.07 */
final class HoursThresholdNotification extends AppNotification
{
    public function __construct(
        private readonly int $projectId,
        private readonly string $projectName,
        private readonly int $percent,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::HoursThreshold;
    }

    protected function title(User $notifiable): string
    {
        return $this->percent >= 100
            ? "{$this->projectName} se quedó sin horas"
            : "{$this->projectName} consumió el {$this->percent}% de sus horas";
    }

    protected function body(User $notifiable): string
    {
        return $this->percent >= 100
            ? 'Nadie puede cronometrar en el proyecto hasta que se carguen más horas.'
            : 'Revisá si hace falta una ampliación antes de que se bloquee.';
    }

    protected function path(): string
    {
        return "/proyectos/{$this->projectId}/horas";
    }
}
