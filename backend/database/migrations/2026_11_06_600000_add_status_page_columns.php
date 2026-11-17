<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-13.06/07: monitoreo's portal_projects live on `projects` (ADR-0002 §6). The slug is in the
     * client's URL (/portal/<slug>); `portal_active` is monitoreo's `active` (shown to the client or not),
     * apart from the RF-03 status.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->after('code_prefix');
            $table->char('primary_color', 7)->nullable();
            // The logo is a file on the private disk (monitoreo kept it as a BLOB).
            $table->string('logo_path')->nullable();
            $table->string('logo_type', 32)->nullable();
            $table->dateTime('logo_updated_at')->nullable();
            $table->string('domain', 253)->nullable();
            $table->dateTime('domain_expires_at')->nullable();
            $table->date('domain_expires_manual')->nullable();
            $table->dateTime('domain_checked_at')->nullable();
            $table->unsignedTinyInteger('domain_notice_level')->default(0);
            $table->string('errors_url', 512)->nullable();
            // EncryptedFor, write-only: never returned (only `has_errors_token`).
            $table->text('errors_token')->nullable();
            $table->string('discord_channel_id', 20)->nullable();
            $table->boolean('portal_active')->default(true);

            $table->unique(['company_id', 'slug']);
        });

        // Projects created before this column: their code is unique per company, so it is a free slug.
        DB::table('projects')->whereNull('slug')->update(['slug' => DB::raw('code_prefix')]);

        // The lead developer gets mentioned in the project's Discord notices (D-054, Phase 7).
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_user_id', 20)->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_user_id');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'slug']);
            $table->dropColumn([
                'slug', 'primary_color', 'logo_path', 'logo_type', 'logo_updated_at', 'domain', 'domain_expires_at',
                'domain_expires_manual', 'domain_checked_at', 'domain_notice_level', 'errors_url', 'errors_token',
                'discord_channel_id', 'portal_active',
            ]);
        });
    }
};
