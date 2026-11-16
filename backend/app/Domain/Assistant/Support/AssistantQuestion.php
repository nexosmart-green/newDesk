<?php

namespace App\Domain\Assistant\Support;

/** One turn for the engine. `$token` is the asker's ephemeral MCP token: never logged, never written to disk. */
final class AssistantQuestion
{
    public function __construct(
        public readonly string $text,
        public readonly string $systemPrompt,
        public readonly string $model,
        public readonly float $budgetUsd,
        public readonly string $token,
        public readonly ?string $sessionId = null,
    ) {}

    /** Without the token, for logs and errors. */
    public function __debugInfo(): array
    {
        return ['model' => $this->model, 'budget_usd' => $this->budgetUsd, 'session_id' => $this->sessionId];
    }
}
