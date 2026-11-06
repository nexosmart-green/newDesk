<?php

namespace App\Domain\Monitoring\Discord;

use RuntimeException;

/**
 * A failed Discord call, worded for the panel (monitoreo discord.StatusError). It never carries the
 * bot token. `status` is Discord's answer (0: none); a 429 brings Discord's `retry_after`.
 */
final class DiscordError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('El bot de Discord no está configurado.');
    }

    public static function status(int $status, int $retryAfter = 0): self
    {
        return new self(match ($status) {
            404 => 'Discord respondió 404: ese canal no existe',
            403 => 'Discord respondió 403: el bot no tiene acceso a ese canal (falta darle permiso al rol Monitoreo)',
            401 => 'Discord respondió 401: el token del bot no es válido',
            429 => 'Discord limitó los envíos (HTTP 429)',
            default => "Discord respondió HTTP {$status}",
        }, $status, $retryAfter);
    }
}
