<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-13.04/05. Same shapes as monitoreo's alerts, site_checks and site_check_history; ids are
     * plain bigints because the importer keeps monitoreo's (CA-13.23).
     */
    public function up(): void
    {
        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->string('rule', 64);
            $table->string('target');
            $table->string('severity', 16);
            $table->string('state', 16);
            $table->string('message', 512);
            $table->string('value', 64);
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('last_notified_at')->nullable();

            $table->index(['server_id', 'rule', 'state']);
            $table->index(['state', 'first_seen_at']);
            $table->index(['state', 'last_seen_at']);
        });

        Schema::create('monitoring_site_checks', function (Blueprint $table) {
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->string('server_name');
            $table->foreignId('company_id')->constrained();
            $table->string('url', 512);
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('ok');
            $table->string('error')->nullable();
            $table->dateTime('ssl_expires_at')->nullable();
            $table->dateTime('checked_at');

            $table->primary(['server_id', 'server_name']);
        });

        Schema::create('monitoring_site_check_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->string('server_name');
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('ok');
            $table->dateTime('checked_at');

            $table->index(['server_id', 'server_name', 'checked_at'], 'monitoring_site_history_name_checked_index');
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_site_check_history');
        Schema::dropIfExists('monitoring_site_checks');
        Schema::dropIfExists('monitoring_alerts');
    }
};
