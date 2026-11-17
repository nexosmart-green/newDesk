<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module access arrived after roles existed: keep today's behavior (everything reachable) for every
 * existing role. From now on the Admin turns modules off per role in Configuración → Roles.
 */
return new class extends Migration
{
    private const MODULES = [
        'module.projects', 'module.time', 'module.clients', 'module.people', 'module.calendar', 'module.reports',
    ];

    public function up(): void
    {
        $rows = [];
        // The client portal role (RF-13.07) has no permission at all.
        foreach (DB::table('roles')->where('name', '!=', 'Cliente')->pluck('id') as $roleId) {
            foreach (self::MODULES as $permission) {
                $rows[] = ['role_id' => $roleId, 'permission' => $permission];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', self::MODULES)->delete();
    }
};
