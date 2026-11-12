<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Notifications\QueuedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use QueuedMail;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $query = http_build_query(['token' => $this->token, 'email' => $notifiable->email]);
        $url = rtrim((string) config('newdesk.frontend_url'), '/').'/restablecer-contrasena?'.$query;

        return (new MailMessage)
            ->subject('Restablecer tu contraseña')
            ->greeting("Hola {$notifiable->first_name}")
            ->line('Alguien pidió restablecer la contraseña de tu cuenta de newDesk.')
            ->action('Elegir una contraseña nueva', $url)
            ->line('Si no lo pediste, ignorá este correo.');
    }
}
