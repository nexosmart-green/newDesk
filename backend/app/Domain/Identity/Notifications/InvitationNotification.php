<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Notifications\QueuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use QueuedMail;

    public function __construct(private readonly string $token, private readonly string $invitedBy) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $company = $notifiable->company->name;
        $url = rtrim((string) config('newdesk.frontend_url'), '/').'/invitacion/'.$this->token;

        return (new MailMessage)
            ->subject("Te invitaron a {$company}")
            ->greeting("Hola {$notifiable->first_name}")
            ->line("{$this->invitedBy} te invitó a usar el espacio de trabajo de {$company}.")
            ->action('Crear mi contraseña', $url)
            ->line('El enlace vence en 7 días.');
    }
}
