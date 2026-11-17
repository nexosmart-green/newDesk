<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

final class UpdateRole
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<Permission>  $permissions */
    public function __invoke(User $actor, Role $role, string $name, array $permissions): Role
    {
        if ($role->is_system) {
            throw new BusinessRuleViolation(
                "El rol {$role->name} es del sistema: siempre tiene todos los permisos y no se edita.",
                'system_role_locked',
            );
        }
        if ($role->isPortal()) {
            throw new BusinessRuleViolation("El rol {$role->name} es el de los usuarios del portal de clientes y no se edita.", 'system_role_locked');
        }

        return DB::transaction(function () use ($actor, $role, $name, $permissions) {
            $before = ['name' => $role->name, 'permissions' => $role->permissionValues()];

            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);

            ($this->recordActivity)('role.updated', $role, $actor, $before, [
                'name' => $role->name,
                'permissions' => $role->permissionValues(),
            ]);

            return $role;
        });
    }
}
