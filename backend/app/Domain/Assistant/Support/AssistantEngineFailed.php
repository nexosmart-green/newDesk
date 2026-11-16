<?php

namespace App\Domain\Assistant\Support;

use RuntimeException;

/** The engine could not answer. The message is for the person (voseo) and never carries the token. */
final class AssistantEngineFailed extends RuntimeException
{
    public function __construct(string $message, public readonly float $costUsd = 0.0, public readonly ?string $sessionId = null)
    {
        parent::__construct($message);
    }
}
