<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\CreateRole;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\People\Models\EmployeeProfile;
use Illuminate\Database\Seeder;

/**
 * Local development only: someone who manages shifts without being Admin (Admin needs 2FA), with the
 * collaborator reporting to her. Lets Turnos, Turnos fijos and the team calendar be tried — and the E2E run.
 * Idempotent: `php artisan db:seed --class=ShiftCoordinatorSeeder`.
 */
class ShiftCoordinatorSeeder extends Seeder
{
    public const EMAIL = 'coordinador@nexosmart.com';

    public function run(CreateRole $createRole): void
    {
        $admin = User::query()->where('email', 'admin@nexosmart.com')->first();
        if ($admin === null) {
            return;
        }

        $role = Role::query()->where('company_id', $admin->company_id)->where('name', 'Coordinación')->first()
            ?? $createRole($admin, 'Coordinación', [...Permission::modules(), Permission::ShiftsManage, Permission::TimeTrack]);

        $coordinator = User::query()->where('email', self::EMAIL)->first()
            ?? User::factory()->forRole($role)->create(['first_name' => 'Carla', 'last_name' => 'Coordinadora', 'email' => self::EMAIL]);

        $collaborators = User::query()->where('company_id', $admin->company_id)
            ->whereHas('role', fn ($query) => $query->where('name', DefaultRoles::COLLABORATOR))
            ->get();

        foreach ($collaborators as $collaborator) {
            $profile = EmployeeProfile::query()->forCompany($admin->company_id)
                ->firstOrNew(['user_id' => $collaborator->id], ['company_id' => $admin->company_id]);
            if ($profile->reports_to_user_id === null) {
                $profile->forceFill(['reports_to_user_id' => $coordinator->id])->save();
            }
        }
    }
}
