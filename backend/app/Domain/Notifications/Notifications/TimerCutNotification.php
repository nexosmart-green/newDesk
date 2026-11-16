<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Shared\Support\Duration;

/** RF-09.05 / RF-05.06 / RF-05.10 */
final class TimerCutNotification extends AppNotification
{
    public function __construct(
        private readonly int $timeEntryId,
        private readonly string $taskCode,
        private readonly string $personName,
        private readonly int $seconds,
        private readonly string $reason,
        private readonly bool $toOwner,
    ) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::TimerCut;
    }

    protected function title(User $notifiable): string
    {
        return $this->toOwner
            ? "Tu cronómetro en {$this->taskCode} se detuvo"
            : "Se cortó el cronómetro de {$this->personName} en {$this->taskCode}";
    }

    protected function body(User $notifiable): string
    {
        $hours = Duration::humanize($this->seconds);

        return $this->toOwner
            ? "Motivo: {$this->reason}. Se registraron {$hours}: completá qué hiciste para poder iniciar otro cronómetro."
            : "Motivo: {$this->reason}. Se registraron {$hours}.";
    }

    protected function path(): string
    {
        return $this->toOwner ? "/mi-semana?revisar={$this->timeEntryId}" : '/';
    }
}
