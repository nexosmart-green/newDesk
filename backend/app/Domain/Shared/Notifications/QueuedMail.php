<?php

namespace App\Domain\Shared\Notifications;

use Illuminate\Queue\Middleware\RateLimited;

/**
 * Standard for every e-mail: sent from the `mail` queue, throttled to 1/s (RateLimiter `mail`).
 * Use in a Notification that implements ShouldQueue.
 */
trait QueuedMail
{
    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'mail', 'database' => 'default', 'broadcast' => 'default'];
    }

    /** @return list<object> */
    public function middleware(object $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new RateLimited('mail')] : [];
    }
}
