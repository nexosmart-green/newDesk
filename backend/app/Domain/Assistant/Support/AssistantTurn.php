<?php

namespace App\Domain\Assistant\Support;

use App\Domain\Assistant\Actions\RecordAssistantProposal;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;

/**
 * Request-scoped: set by AuthorizeMcpToken when the MCP request comes from an assistant turn. While it is
 * active, write tools do not run: they leave a proposal for the person to confirm (RF-13.02).
 */
final class AssistantTurn
{
    private ?AssistantMessage $message = null;

    public function __construct(private readonly RecordAssistantProposal $recordProposal) {}

    public function begin(AssistantMessage $message): void
    {
        $this->message = $message;
    }

    /** Every MCP request starts outside a turn: a long-lived process must never carry one over to another token. */
    public function clear(): void
    {
        $this->message = null;
    }

    public function active(): bool
    {
        return $this->message !== null;
    }

    /** The answer being written in this turn (reports generated now are attached to it). */
    public function answer(): ?AssistantMessage
    {
        return $this->message;
    }

    /** @param  array<string, mixed>  $arguments  as the tool received them, to prepare it again on confirmation */
    public function propose(string $tool, array $arguments, string $summary): AssistantProposal
    {
        return ($this->recordProposal)($this->message, $tool, $arguments, $summary);
    }
}
