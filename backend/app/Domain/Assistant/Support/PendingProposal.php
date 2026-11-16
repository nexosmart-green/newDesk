<?php

namespace App\Domain\Assistant\Support;

use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/** A proposal is resolved once, and only while it has not expired (RF-13.02). */
final class PendingProposal
{
    /** Call inside the resolving transaction: whoever comes second waits on the row and sees it resolved. */
    public function lock(AssistantProposal $proposal): AssistantProposal
    {
        $locked = AssistantProposal::query()->forCompany($proposal->company_id)->lockForUpdate()->findOrFail($proposal->id);
        if (! $locked->isPending()) {
            throw new BusinessRuleViolation('Esa propuesta ya fue resuelta.', 'proposal_already_resolved');
        }

        return $locked;
    }

    /** Marks it expired (outside any transaction, so it sticks) and says so. */
    public function expireIfDue(AssistantProposal $proposal): void
    {
        if (! $proposal->isPending() || ! $proposal->isExpired()) {
            return;
        }

        AssistantProposal::query()->whereKey($proposal->id)->where('status', ProposalStatus::Pending)
            ->update(['status' => ProposalStatus::Expired, 'resolved_at' => now()]);

        throw new BusinessRuleViolation('La propuesta venció: pedíselo de nuevo al asistente.', 'proposal_expired');
    }
}
