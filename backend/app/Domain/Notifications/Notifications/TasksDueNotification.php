<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;

/** RF-09.03: one daily summary per person instead of one message per task. */
final class TasksDueNotification extends AppNotification
{
    /**
     * @param  list<string>  $dueTomorrow  "bhn-60 Título"
     * @param  list<string>  $overdue
     */
    public function __construct(private readonly array $dueTomorrow, private readonly array $overdue) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::TaskDue;
    }

    protected function title(User $notifiable): string
    {
        $parts = array_filter([
            $this->overdue !== [] ? count($this->overdue).' vencida(s)' : null,
            $this->dueTomorrow !== [] ? count($this->dueTomorrow).' vence(n) mañana' : null,
        ]);

        return 'Tus tareas: '.implode(' · ', $parts);
    }

    protected function body(User $notifiable): string
    {
        $lines = [];
        if ($this->overdue !== []) {
            $lines[] = 'Vencidas: '.implode(', ', $this->overdue).'.';
        }
        if ($this->dueTomorrow !== []) {
            $lines[] = 'Vencen mañana: '.implode(', ', $this->dueTomorrow).'.';
        }

        return implode(' ', $lines);
    }

    protected function path(): string
    {
        return '/';
    }
}
