<?php

namespace App\Http\Resources;

use App\Domain\Assistant\Models\AssistantConversation;
use Illuminate\Http\Request;

/** @mixin AssistantConversation */
class AssistantConversationResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'channel' => $this->channel->value,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
