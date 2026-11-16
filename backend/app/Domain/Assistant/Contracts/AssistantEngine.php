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
}
