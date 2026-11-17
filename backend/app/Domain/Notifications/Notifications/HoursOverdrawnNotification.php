<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;

/** RF-05.19 / CA-05.19f: same channel and rules as the 80/100% alerts (RF-09.04). */
final class HoursOverdrawnNotification extends AppNotification
{
    public function __construct(
        private readonly int $projectId,
        private readonly string $projectName,
        private readonly string $personName,
        private readonly string $overdrawn,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::HoursThreshold;
    }

    protected function title(User $notifiable): string
    {
        return "{$this->projectName} quedó en negativo";
    }

    protected function body(User $notifiable): string
    {
        return "{$this->personName} repartió su día y cargó {$this->overdrawn} más de lo disponible. Nadie puede cronometrar en el proyecto hasta que se carguen más horas.";
    }

    protected function path(): string
    {
        return "/proyectos/{$this->projectId}/horas";
    }
}
