<?php

namespace App\Domain\Assistant\Support;

use RuntimeException;

/** The engine could not answer. The message is for the person (voseo) and never carries the token. */
final class AssistantEngineFailed extends RuntimeException
{
    /**
     * @param  bool  $mayRetryFresh  the engine refused before doing any work (e.g. a session this machine no longer
     *                               has): asking again without `--resume` is cheap. Never after a timeout.
     */
    public function __construct(
        string $message,
        public readonly float $costUsd = 0.0,
        public readonly ?string $sessionId = null,
        public readonly bool $mayRetryFresh = false,
    ) {
        parent::__construct($message);
    }
}
