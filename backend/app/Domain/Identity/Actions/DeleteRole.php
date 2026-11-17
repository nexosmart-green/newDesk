<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

final class DeleteRole
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Role $role): void
    {
        if ($role->is_system || $role->isPortal()) {
            throw new BusinessRuleViolation("El rol {$role->name} es del sistema y no se puede borrar.", 'system_role_locked');
        }

        DB::transaction(function () use ($actor, $role) {
            if ($role->users()->lockForUpdate()->exists()) {
                throw new BusinessRuleViolation(
                    "El rol {$role->name} tiene usuarios asignados. Cambiales el rol antes de borrarlo.",
                    'role_in_use',
                );
            }

            ($this->recordActivity)('role.deleted', $role, $actor, [
                'name' => $role->name,
                'permissions' => $role->permissionValues(),
            ]);

            $role->delete();
        });
    }
}
