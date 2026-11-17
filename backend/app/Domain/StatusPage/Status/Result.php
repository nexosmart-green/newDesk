<?php

namespace App\Domain\StatusPage\Status;

use Carbon\CarbonImmutable;

/**
 * One evaluation of a component (monitoreo portal.Result): the phrase is for the client, the
 * detail for the team.
 */
final class Result
{
    public const PHRASE_OK = 'Funciona normalmente';

    public const PHRASE_NO_ANSWER = 'No responde';

    public const PHRASE_NO_DATA = 'Sin datos recientes';

    public function __construct(
        public readonly string $status,
        public readonly string $phrase = '',
        public readonly string $detail = '',
        public readonly ?int $latencyMs = null,
        public readonly ?int $statusCode = null,
        public readonly ?CarbonImmutable $sslExpiresAt = null,
        /** Timers: when the backup last succeeded. */
        public readonly ?CarbonImmutable $lastOkAt = null,
    ) {}

    public static function unknown(string $detail): self
    {
        return new self(ComponentState::UNKNOWN, self::PHRASE_NO_DATA, $detail);
    }
}
