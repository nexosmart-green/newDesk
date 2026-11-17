<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-14.08: the assistant acts in Discord only for links the person made themselves (/newdesk vincular with a code
 * from their own session). An id an Admin typed in monitoreo still serves for mentions, never to act as someone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('discord_verified_at')->nullable()->after('discord_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_verified_at');
        });
    }
};
