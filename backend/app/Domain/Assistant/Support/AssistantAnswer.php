<?php

namespace App\Domain\Assistant\Support;

/** What the engine answered. With a subscription the cost is Claude Code's estimate (a usage guard, RF-13.09). */
final class AssistantAnswer
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $sessionId,
        public readonly float $costUsd,
        public readonly int $durationMs,
    ) {}
}
