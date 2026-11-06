<?php

namespace App\Domain\Companies\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a company with its base roles and first Admin. Used by `newdesk:install`
 * (production bootstrap) and by tests — never by a seeder only, so prod gets the same data.
 */
final class ProvisionCompany
{
    /**
     * @param  array{name: string, currency?: string, timezone?: string}  $company
     * @param  array{first_name: string, last_name: string, email: string, password: string}  $admin
     */
    public function __invoke(array $company, array $admin): User
    {
        return DB::transaction(function () use ($company, $admin) {
            $newCompany = Company::query()->create($company);

            $adminRole = null;
            foreach (DefaultRoles::definitions() as $name => $permissions) {
                $role = Role::query()->create([
                    'company_id' => $newCompany->id,
                    'name' => $name,
                    'is_system' => in_array($name, DefaultRoles::SYSTEM, true),
                ]);
                $role->syncPermissions($permissions);
                if ($name === DefaultRoles::ADMIN) {
                    $adminRole = $role;
                }
            }

            return User::query()->create([
                ...$admin,
                'company_id' => $newCompany->id,
                'role_id' => $adminRole?->id,
                'status' => UserStatus::Active,
            ]);
        });
    }
}
