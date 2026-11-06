<?php

namespace App\Domain\People\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\RoleGrantGuard;
use App\Domain\People\Events\UserDeactivated;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-01.10: deactivate, never delete. Hours and authorship stay; login, MCP tokens and
 * assignment selectors are gone from the next request on.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly RoleGrantGuard $guard,
    ) {}

    public function __invoke(User $actor, User $user): User
    {
        if ($actor->is($user)) {
            throw new BusinessRuleViolation('No podés darte de baja a vos mismo.', 'cannot_deactivate_self');
        }
        $this->guard->assertCanManage($actor, $user);

        if ($user->status === UserStatus::Inactive) {
            return $user;
        }

        DB::transaction(function () use ($actor, $user) {
            if ($user->role->is_system && ! $user->role->users()->where('status', 'active')->whereKeyNot($user->id)->lockForUpdate()->exists()) {
                throw new BusinessRuleViolation('No se puede dar de baja al último administrador.', 'last_admin');
            }

            $before = $user->status->value;
            $user->forceFill(['status' => UserStatus::Inactive, 'remember_token' => null])->save();
            $user->tokens()->delete();

            ($this->recordActivity)('user.deactivated', $user, $actor, ['status' => $before], ['status' => UserStatus::Inactive->value]);

            UserDeactivated::dispatch($user, $actor);
        });

        return $user;
    }
}
