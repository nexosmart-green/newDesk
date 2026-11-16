<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-13: the existing Admin roles (system roles) get `assistant.use`; other roles opt in from Configuración → Roles.
 * Time entries started from a confirmed proposal record channel `assistant`, longer than the old 8 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('roles')->where('is_system', true)->pluck('id')
            ->map(fn (int $roleId) => ['role_id' => $roleId, 'permission' => 'assistant.use'])->all();
        DB::table('role_permissions')->insertOrIgnore($rows);

        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('channel', 16)->default('web')->change();
        });
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'assistant.use')->delete();
    }
};
