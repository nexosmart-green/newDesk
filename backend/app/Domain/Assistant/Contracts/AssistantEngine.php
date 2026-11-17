<?php

namespace App\Domain\Assistant\Contracts;

use App\Domain\Assistant\Support\AssistantAnswer;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;

/**
 * The model that answers a turn, reaching newDesk ONLY through its MCP with the asker's ephemeral token.
 * Adapter: App\Infrastructure\ClaudeCode\ClaudeCodeEngine (`claude -p`). Tests fake the process, never call it.
 */
interface AssistantEngine
{
    /** @throws AssistantEngineFailed with a message a person can read */
    public function answer(AssistantQuestion $question): AssistantAnswer;

    /**
     * Writes a text from the given material only: no MCP, no tools, no session (the weekly summary, RF-14.06).
     *
     * @throws AssistantEngineFailed
     */
    public function compose(string $instructions, string $text, string $model, float $budgetUsd): AssistantAnswer;
}
