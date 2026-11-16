<?php

namespace App\Domain\Assistant\Contracts;

use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\ProposalOutcome;
use App\Domain\Identity\Models\User;

/**
 * Runs a confirmed proposal as the person who confirmed it: validated and authorized again with their role at
 * that moment, through the same tool that proposed it. Adapter: App\Mcp\Support\McpProposalExecutor.
 */
interface ProposalExecutor
{
    public function execute(AssistantProposal $proposal, User $user): ProposalOutcome;
}
