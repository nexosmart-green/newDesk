<?php

namespace App\Http\Resources;

use App\Domain\Assistant\Models\AssistantMessage;
use Illuminate\Http\Request;

/** @mixin AssistantMessage */
class AssistantMessageResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'status' => $this->status->value,
            'error' => $this->error,
            'created_at' => $this->created_at->toIso8601String(),
            'proposals' => AssistantProposalResource::collection($this->whenLoaded('proposals')),
            'files' => AssistantFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
