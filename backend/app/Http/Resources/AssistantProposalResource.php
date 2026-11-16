<?php

namespace App\Http\Resources;

use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantProposal;
use Illuminate\Http\Request;

/** @mixin AssistantProposal */
class AssistantProposalResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $expired = $this->status === ProposalStatus::Pending && $this->isExpired();

        return [
            'id' => $this->id,
            'summary' => $this->summary,
            'status' => $expired ? ProposalStatus::Expired->value : $this->status->value,
            'error' => $this->error,
            'expires_at' => $this->expires_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
