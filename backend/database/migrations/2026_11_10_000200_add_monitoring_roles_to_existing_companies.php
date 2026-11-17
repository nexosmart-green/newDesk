<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring (RF-13, ADR-0002 §12) arrived after companies existed: give their Admin the monitoring
 * permissions and create the Desarrollador and Cliente roles they would have got when provisioned.
 */
return new class extends Migration
{
    private const ADMIN = ['monitoring.view', 'monitoring.manage'];

    private const DEVELOPER = ['module.projects', 'module.time', 'module.people', 'module.calendar', 'tasks.assign', 'time.track', 'mcp.use', 'monitoring.view'];

    public function up(): void
    {
        foreach (DB::table('roles')->where('is_system', true)->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(array_map(fn (string $permission) => ['role_id' => $roleId, 'permission' => $permission], self::ADMIN));
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach (['Desarrollador' => self::DEVELOPER, 'Cliente' => []] as $name => $permissions) {
                if (DB::table('roles')->where('company_id', $companyId)->where('name', $name)->exists()) {
                    continue;
                }
                $roleId = DB::table('roles')->insertGetId(['company_id' => $companyId, 'name' => $name, 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('role_permissions')->insert(array_map(fn (string $permission) => ['role_id' => $roleId, 'permission' => $permission], $permissions));
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', self::ADMIN)->delete();
    }
};
