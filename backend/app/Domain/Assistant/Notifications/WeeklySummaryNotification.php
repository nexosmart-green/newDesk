<?php

namespace App\Domain\Assistant\Notifications;

use App\Domain\Assistant\Models\AssistantWeeklySummary;
use App\Domain\Shared\Notifications\QueuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** RF-14.06: the weekly summary by e-mail (mail queue, throttled like every e-mail). */
class WeeklySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use QueuedMail;

    public function __construct(private readonly string $companyName, private readonly AssistantWeeklySummary $summary) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Resumen de la semana del {$this->summary->week_from->format('d/m')} al {$this->summary->week_to->format('d/m')} en {$this->companyName}");
        foreach (preg_split('/\n{2,}/', trim($this->summary->content)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->action('Abrir el asistente', rtrim((string) config('newdesk.frontend_url'), '/').'/asistente');
    }
}
