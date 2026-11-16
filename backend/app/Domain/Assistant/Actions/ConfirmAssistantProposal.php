<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Contracts\ProposalExecutor;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\PendingProposal;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\ActivityContext;
use Illuminate\Support\Facades\DB;

/**
 * RF-13.02: the person who asked confirms a proposal. It runs once (row lock: a double click or a second
 * channel sees it resolved), prepared again with the role they have NOW, and is recorded with source
 * `assistant` and the channel (RF-13.04). If the action refuses it, the proposal ends `failed` with the reason.
 * Who may confirm is AssistantProposalPolicy::resolve.
 */
final class ConfirmAssistantProposal
{
    public function __construct(
        private readonly PendingProposal $pending,
        private readonly ProposalExecutor $executor,
        private readonly ActivityContext $context,
    ) {}

    public function __invoke(User $actor, AssistantProposal $proposal, AssistantChannel $channel): AssistantProposal
    {
        $this->pending->expireIfDue($proposal);

        return DB::transaction(function () use ($actor, $proposal, $channel) {
            $proposal = $this->pending->lock($proposal);

            $this->context->useAssistant($channel->value);
            $outcome = $this->executor->execute($proposal, $actor);

            $proposal->forceFill([
                'status' => $outcome->succeeded ? ProposalStatus::Confirmed : ProposalStatus::Failed,
                'result' => $outcome->result,
                'error' => $outcome->error !== null ? mb_substr($outcome->error, 0, 500) : null,
                'resolved_channel' => $channel->value,
                'resolved_at' => now(),
            ])->save();

            return $proposal;
        });
    }
}
