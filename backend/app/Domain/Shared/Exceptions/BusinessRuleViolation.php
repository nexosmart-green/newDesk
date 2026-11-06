<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * A domain rule refused the operation (e.g. "Sin horas disponibles en bhnews: pedí una ampliación").
 *
 * The message is user-facing and identical for web and MCP (RF-12.07), so write it for a human.
 * `code` is a stable machine identifier the frontend/MCP can branch on.
 */
class BusinessRuleViolation extends RuntimeException
{
    /** @param  array<string, mixed>  $context */
    public function __construct(
        string $message,
        public readonly string $ruleCode,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
