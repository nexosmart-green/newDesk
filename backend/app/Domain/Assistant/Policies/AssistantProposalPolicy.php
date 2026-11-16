<?php

namespace App\Domain\Assistant\Policies;

use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the person who asked confirms or cancels (CA-13.08): anyone else does not learn it exists (404).
 */
class AssistantProposalPolicy
{
    public function resolve(User $user, AssistantProposal $proposal): Response
    {
        if ($proposal->user_id !== $user->id || $proposal->company_id !== $user->company_id) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::AssistantUse)
            ? Response::allow()
            : Response::deny('Tu rol no tiene habilitado el asistente de IA.', 'assistant_not_allowed');
    }
}
