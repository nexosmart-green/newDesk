<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\RoleGrantGuard;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Takes effect on the next request, for web sessions and MCP tokens alike (RF-12.03).
 */
final class ChangeUserRole
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly RoleGrantGuard $guard,
    ) {}

    public function __invoke(User $actor, User $user, Role $role): User
    {
        if ($user->role_id === $role->id) {
            return $user;
        }

        DB::transaction(function () use ($actor, $user, $role) {
            $previous = $user->role;

            if ($previous->is_system && $this->isLastActiveMember($previous, $user)) {
                throw new BusinessRuleViolation(
                    "No se puede quitar el último {$previous->name}: la empresa quedaría sin administrador.",
                    'last_admin',
                );
            }

            $this->guard->assertNotSelf($actor, $user);
            $this->guard->assertCanManage($actor, $user);
            $this->guard->assertCanGrant($actor, $role);

            $user->forceFill(['role_id' => $role->id])->save();
            $user->setRelation('role', $role);

            ($this->recordActivity)('user.role_changed', $user, $actor, ['role' => $previous->name], ['role' => $role->name]);
        });

        return $user;
    }

    private function isLastActiveMember(Role $role, User $user): bool
    {
        return ! $role->users()->where('status', 'active')->whereKeyNot($user->id)->lockForUpdate()->exists();
    }
}
