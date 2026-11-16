<?php

namespace App\Domain\Assistant\Policies;

use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/** RF-13.05: a generated report is downloaded only by whoever asked for it, while it has not expired. */
class AssistantFilePolicy
{
    public function download(User $user, AssistantFile $file): Response
    {
        if ($file->user_id !== $user->id || $file->company_id !== $user->company_id || $file->expires_at->isPast()) {
            return Response::denyAsNotFound();
        }

        return $user->hasPermission(Permission::AssistantUse)
            ? Response::allow()
            : Response::deny('Tu rol no tiene habilitado el asistente de IA.', 'assistant_not_allowed');
    }
}
