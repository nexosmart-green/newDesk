<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;

/**
 * RF-13.02: keeps a write the assistant prepared (already validated and authorized for the person) without
 * running it. Confirming it is ConfirmAssistantProposal.
 */
final class RecordAssistantProposal
{
    /** @param  array<string, mixed>  $arguments */
    public function __invoke(AssistantMessage $message, string $tool, array $arguments, string $summary): AssistantProposal
    {
        $message->loadMissing('conversation');

        return AssistantProposal::query()->create([
            'company_id' => $message->company_id,
            'user_id' => $message->conversation->user_id,
            'message_id' => $message->id,
            'tool' => $tool,
            'arguments' => $arguments,
            'summary' => $summary,
            'status' => ProposalStatus::Pending,
            'expires_at' => now()->addHours(AssistantProposal::TTL_HOURS),
        ]);
    }
}
