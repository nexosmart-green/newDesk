<?php

namespace App\Domain\Assistant\Queries;

use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** RF-13.01: my conversations with the assistant, the most recent first. */
final class MyAssistantConversations
{
    /** @return LengthAwarePaginator<int, AssistantConversation> */
    public function __invoke(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return AssistantConversation::query()->forCompany($user->company_id)->where('user_id', $user->id)
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate($perPage);
    }
}
