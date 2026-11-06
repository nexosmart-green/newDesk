<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserInvitation;
use App\Domain\Identity\Notifications\InvitationNotification;
use Illuminate\Support\Str;

/**
 * Creates a fresh invitation token (invalidating previous ones) and e-mails it.
 */
final class IssueInvitation
{
    public function __invoke(User $invitee, User $invitedBy): UserInvitation
    {
        UserInvitation::query()->forCompany($invitee->company_id)
            ->where('user_id', $invitee->id)
            ->whereNull('accepted_at')
            ->delete();

        $token = Str::random(64);

        $invitation = UserInvitation::query()->create([
            'company_id' => $invitee->company_id,
            'user_id' => $invitee->id,
            'invited_by' => $invitedBy->id,
            'token_hash' => UserInvitation::hashToken($token),
            'expires_at' => now()->addDays(UserInvitation::VALID_DAYS),
        ]);

        $invitee->notify(new InvitationNotification($token, $invitedBy->fullName()));

        return $invitation;
    }
}
