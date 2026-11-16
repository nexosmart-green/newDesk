<?php

namespace App\Domain\Assistant\Policies;

use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/** A conversation is private: only its owner sees it (404 for anyone else), and only with `assistant.use`. */
class AssistantConversationPolicy
{
    public function view(User $user, AssistantConversation $conversation): Response
    {
        if ($conversation->user_id !== $user->id || $conversation->company_id !== $user->company_id) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::AssistantUse)
            ? Response::allow()
            : Response::deny('Tu rol no tiene habilitado el asistente de IA.', 'assistant_not_allowed');
    }
}
