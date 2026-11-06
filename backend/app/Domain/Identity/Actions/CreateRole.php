<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

final class CreateRole
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<Permission>  $permissions */
    public function __invoke(User $actor, string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($actor, $name, $permissions) {
            $role = Role::query()->create(['company_id' => $actor->company_id, 'name' => $name]);
            $role->syncPermissions($permissions);

            ($this->recordActivity)('role.created', $role, $actor, after: [
                'name' => $role->name,
                'permissions' => $role->permissionValues(),
            ]);

            return $role;
        });
    }
}
