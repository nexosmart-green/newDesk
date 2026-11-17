<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Support\Facades\DB;

// A company provisioned before monitoring existed gets its roles and the Admin's monitoring permissions.
it('adds the monitoring roles and permissions to companies that already existed', function () {
    $admin = provisionCompany();
    Role::query()->forCompany($admin->company_id)->whereIn('name', [DefaultRoles::DEVELOPER, DefaultRoles::CLIENT])->delete();
    DB::table('role_permissions')->whereIn('permission', ['monitoring.view', 'monitoring.manage'])->delete();

    (require database_path('migrations/2026_11_10_000200_add_monitoring_roles_to_existing_companies.php'))->up();

    $roles = Role::query()->forCompany($admin->company_id)->get()->keyBy('name');
    expect($admin->fresh()->hasPermission(Permission::MonitoringManage))->toBeTrue()
        ->and($roles[DefaultRoles::DEVELOPER]->grants(Permission::MonitoringView))->toBeTrue()
        ->and($roles[DefaultRoles::DEVELOPER]->grants(Permission::ModuleTime))->toBeTrue()
        ->and($roles[DefaultRoles::CLIENT]->permissionValues())->toBe([])
        ->and($roles[DefaultRoles::CLIENT]->is_system)->toBeFalse();
});
