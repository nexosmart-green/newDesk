<?php

namespace App\Mcp\Support;

use Closure;
use Laravel\Mcp\Response;

/**
 * A write that is already validated, resolved and authorized, but not executed yet (RF-13.02).
 * `summary` is what a person reads before confirming it ("Cargar 20 h a bhn — motivo: …").
 */
final class PreparedWrite
{
    /** @param  Closure(): Response  $execute */
    private function __construct(public readonly string $summary, private readonly Closure $execute) {}

    /** @param  Closure(): Response  $execute */
    public static function of(string $summary, Closure $execute): self
    {
        return new self($summary, $execute);
    }

    public function execute(): Response
    {
        return ($this->execute)();
    }
}
