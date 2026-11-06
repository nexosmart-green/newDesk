<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

final class ResendInvitation
{
    public function __construct(private readonly IssueInvitation $issueInvitation) {}

    public function __invoke(User $actor, User $invitee): void
    {
        if ($invitee->status !== UserStatus::Invited) {
            throw new BusinessRuleViolation('Este usuario ya aceptó la invitación.', 'invitation_already_accepted');
        }

        ($this->issueInvitation)($invitee, $actor);
    }
}
