<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The portal login code (RF-13.07). The user asked for it, so it is not gated by MONITORING_NOTIFY.
 * It goes to the stored address, never to the one typed (D-076).
 */
class PortalLoginCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
        $this->onQueue('mail');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de acceso a Monitoreo')
            ->greeting('Hola')
            ->line('Tu código para entrar a Monitoreo es:')
            ->line("**{$this->code}**")
            ->line('Usalo en el mismo navegador donde lo pediste.')
            ->line('Vence en 10 minutos. Si no lo pediste, ignorá este mail.')
            ->salutation('Equipo nexosmart');
    }
}
