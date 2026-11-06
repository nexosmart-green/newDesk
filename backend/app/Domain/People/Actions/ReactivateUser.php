<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;

final class ReactivateUser
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, User $user): User
    {
        if ($user->status !== UserStatus::Inactive) {
            return $user;
        }

        // Someone who never accepted the invitation has no password: back to "invited".
        $status = $user->getAuthPassword() === null ? UserStatus::Invited : UserStatus::Active;
        $user->forceFill(['status' => $status])->save();

        ($this->recordActivity)('user.reactivated', $user, $actor, ['status' => UserStatus::Inactive->value], ['status' => $status->value]);

        return $user;
    }
}
