<?php

namespace App\Domain\Assistant\Queries;

use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use Illuminate\Database\Eloquent\Collection;

/** The thread of a conversation, oldest first, with each answer's proposals and report files. */
final class ConversationMessages
{
    public const RELATIONS = ['proposals', 'files'];

    /** @return Collection<int, AssistantMessage> */
    public function __invoke(AssistantConversation $conversation): Collection
    {
        return $conversation->messages()->with(self::RELATIONS)->orderBy('id')->get();
    }
}
