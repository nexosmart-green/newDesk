<?php

namespace App\Domain\Support\Contracts;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * The server's Claude (root's `claude -p`, monitoreo D-074): it only rewrites the text it gets, with no
 * tools. Implemented by App\Infrastructure\Claude\UnixSocketClaudeRunner.
 */
interface ClaudeRunner
{
    /** Whether it is connected at all (the socket unit is installed). */
    public function available(): bool;

    /**
     * Claude's answer to the prompt.
     *
     * @throws BusinessRuleViolation 409 when root's Claude is logged out, 502 when it failed or gave no answer
     */
    public function run(string $prompt): string;
}
