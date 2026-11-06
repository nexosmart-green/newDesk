<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Shared\Notifications\QueuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Template for every RF-09 notification: always in the bell (database), e-mail per preference,
 * same title/body in both. Subclasses only describe the message (Template Method).
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use QueuedMail;

    abstract public function event(): NotificationEvent;

    abstract protected function title(User $notifiable): string;

    abstract protected function body(User $notifiable): string;

    /** SPA path the notification opens, e.g. `/tareas/bhn-60`. */
    abstract protected function path(): string;

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        // Domain events fire inside transactions: never notify about something that rolled back.
        $this->afterCommit();

        return NotificationPreference::wantsEmail($notifiable, $this->event()) ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string> */
    public function toArray(User $notifiable): array
    {
        return [
            'event' => $this->event()->value,
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'path' => $this->path(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting("Hola {$notifiable->first_name}")
            ->line($this->body($notifiable))
            ->action('Abrir en newDesk', rtrim((string) config('newdesk.frontend_url'), '/').$this->path());
    }

    public function databaseType(User $notifiable): string
    {
        return $this->event()->value;
    }
}
