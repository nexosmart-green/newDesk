<?php

namespace App\Domain\Monitoring\Agent;

use RuntimeException;

/**
 * A failed call to an agent. The message is already the short text the UI shows
 * (monitoreo agentclient.ErrorText).
 */
final class AgentError extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const UNSUPPORTED = 'unsupported';

    public const TIMEOUT = 'timeout';

    public const PIN_MISMATCH = 'pin_mismatch';

    public const OTHER = 'other';

    public function __construct(public readonly string $kind, string $message)
    {
        parent::__construct(mb_substr($message, 0, 300));
    }

    public static function unauthorized(): self
    {
        return new self(self::UNAUTHORIZED, 'token inválido');
    }

    /** The agent predates the section (404): it answers again only once updated. */
    public static function unsupported(): self
    {
        return new self(self::UNSUPPORTED, 'el agente es anterior a esta función: hay que actualizarlo');
    }

    public static function timeout(): self
    {
        return new self(self::TIMEOUT, 'timeout');
    }

    public static function pinMismatch(): self
    {
        return new self(self::PIN_MISMATCH, 'la huella no coincide');
    }

    public static function other(string $message): self
    {
        return new self(self::OTHER, $message);
    }
}
