<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.01: an Admin registers a person; they become active when they accept the invitation.
 */
final class InviteUser
{
    public function __construct(
        private readonly IssueInvitation $issueInvitation,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array{first_name: string, last_name: string, email: string}  $data */
    public function __invoke(User $actor, array $data, Role $role): User
    {
        $invitee = DB::transaction(function () use ($actor, $data, $role) {
            $invitee = User::query()->create([
                ...$data,
                'company_id' => $actor->company_id,
                'role_id' => $role->id,
                'status' => UserStatus::Invited,
            ]);

            ($this->recordActivity)('user.invited', $invitee, $actor, after: [
                'email' => $invitee->email,
                'role' => $role->name,
            ]);

            return $invitee;
        });

        ($this->issueInvitation)($invitee, $actor);

        return $invitee;
    }
}
