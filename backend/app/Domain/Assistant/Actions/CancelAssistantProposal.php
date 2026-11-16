<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\PendingProposal;
use Illuminate\Support\Facades\DB;

/** RF-13.02: cancelling leaves no change at all (CA-13.02). Who may cancel is AssistantProposalPolicy::resolve. */
final class CancelAssistantProposal
{
    public function __construct(private readonly PendingProposal $pending) {}

    public function __invoke(AssistantProposal $proposal, AssistantChannel $channel): AssistantProposal
    {
        return DB::transaction(function () use ($proposal, $channel) {
            $proposal = $this->pending->lock($proposal);
            $proposal->forceFill([
                'status' => ProposalStatus::Cancelled,
                'resolved_channel' => $channel->value,
                'resolved_at' => now(),
            ])->save();

            return $proposal;
        });
    }
}
