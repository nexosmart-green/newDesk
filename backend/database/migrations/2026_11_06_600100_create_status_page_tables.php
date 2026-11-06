<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-13.06: monitoreo's portal_components, portal_checks, portal_checks_hourly, portal_periods,
     * portal_incident_notes, portal_project_folders and portal_pagespeed, same shapes plus company_id.
     * Ids are kept on import (CA-13.23). The component's monitoring state lives on its row, so a
     * restart keeps streaks and periods.
     */
    public function up(): void
    {
        Schema::create('status_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('label', 120);
            $table->string('parts', 32)->default(''); // client parts it affects: web,app,cms (D-045)
            $table->integer('position')->default(0);
            $table->string('url', 512)->nullable();
            $table->foreignId('server_id')->nullable()->constrained('monitoring_servers')->nullOnDelete();
            $table->string('target')->nullable();
            $table->string('target_owner', 64)->nullable(); // pm2: the daemon's user (D-089)
            $table->integer('max_age_hours')->nullable();
            $table->integer('slow_ms')->nullable();
            $table->string('status', 16)->default('unknown');
            $table->dateTime('status_since')->nullable();
            $table->string('phrase', 120)->default('');
            $table->string('detail')->default('');
            $table->integer('bad_streak')->default(0);
            $table->dateTime('bad_since')->nullable();
            $table->dateTime('down_since')->nullable(); // start of the current run of down results (D-075)
            $table->dateTime('last_checked_at')->nullable();
            $table->dateTime('last_ok_at')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->integer('status_code')->nullable();
            $table->dateTime('ssl_expires_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['project_id', 'position']);
        });

        // One row per http/health probe, kept 7 days (the response-time chart).
        Schema::create('status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->dateTime('checked_at');
            $table->boolean('ok');
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();

            $table->index(['component_id', 'checked_at']);
            $table->index('checked_at');
        });

        Schema::create('status_checks_hourly', function (Blueprint $table) {
            $table->foreignId('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->dateTime('hour');
            $table->foreignId('company_id')->constrained();
            $table->integer('samples');
            $table->integer('failures');
            $table->double('avg_ms')->nullable();
            $table->integer('max_ms')->nullable();

            $table->primary(['component_id', 'hour']);
            $table->index('hour');
        });

        // Degraded/down stretches: the only source of uptime and incidents (D-039).
        Schema::create('status_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->string('status', 16);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('detail')->default('');

            $table->index(['component_id', 'started_at']);
            $table->index('ended_at');
        });

        Schema::create('status_incident_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('period_id')->constrained('status_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author');
            $table->text('body');
            $table->dateTime('created_at');

            $table->index(['period_id', 'created_at']);
        });

        // An inventory folder belongs to one client project at most (D-044).
        Schema::create('status_project_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->string('path', 512);
            $table->dateTime('created_at');

            $table->unique(['server_id', 'path']);
            $table->index('project_id');
        });

        Schema::create('status_pagespeed_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->string('strategy', 8);
            $table->integer('score')->nullable();
            $table->integer('lcp_ms')->nullable();
            $table->double('cls')->nullable();
            $table->integer('inp_ms')->nullable();
            $table->integer('fcp_ms')->nullable();
            $table->integer('tbt_ms')->nullable();
            $table->boolean('field')->default(false);
            $table->string('error')->nullable();
            $table->dateTime('measured_at');

            $table->index(['component_id', 'strategy', 'measured_at']);
            $table->index('measured_at');
        });

        // Technical: which newDesk row each legacy row became (ADR-0002 §9), so the import can run again.
        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');

            $table->unique(['source', 'source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
        Schema::dropIfExists('status_pagespeed_runs');
        Schema::dropIfExists('status_project_folders');
        Schema::dropIfExists('status_incident_notes');
        Schema::dropIfExists('status_periods');
        Schema::dropIfExists('status_checks_hourly');
        Schema::dropIfExists('status_checks');
        Schema::dropIfExists('status_components');
    }
};
