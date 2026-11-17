<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-13.01/02/03. Same shapes as monitoreo's servers, server_snapshots, metrics_raw and
     * metrics_hourly; ids are plain bigints because the importer keeps monitoreo's (CA-13.23).
     */
    public function up(): void
    {
        Schema::create('monitoring_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 100);
            $table->string('ip', 64);
            $table->unsignedInteger('ssh_port')->nullable();
            $table->unsignedInteger('agent_port');
            // Laravel-encrypted and bound to this column (EncryptedFor, CA-13.01).
            $table->text('token');
            $table->char('tls_fingerprint', 64);
            // base64 SHA-256 of the certificate's public key, derived from the fingerprint (ADR-0002 §2).
            $table->string('tls_spki_pin', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 16)->default('unknown');
            $table->dateTime('last_seen_at')->nullable();
            $table->string('hostname')->nullable();
            $table->string('os')->nullable();
            $table->string('agent_version', 64)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('monitoring_server_snapshots', function (Blueprint $table) {
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->string('section', 32);
            $table->foreignId('company_id')->constrained();
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('fetched_at');
            $table->string('agent_version', 64)->nullable();
            // Raw JSON exactly as the agent sent it.
            $table->longText('data')->nullable();
            $table->longText('warnings')->nullable();
            $table->text('error')->nullable();

            $table->primary(['server_id', 'section']);
        });

        Schema::create('monitoring_metrics_raw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->dateTime('ts');
            foreach (['cpu', 'memory', 'swap', 'disk', 'load1', 'rx_bps', 'tx_bps'] as $column) {
                $table->double($column);
            }

            $table->index(['server_id', 'ts']);
            $table->index('ts');
        });

        Schema::create('monitoring_metrics_hourly', function (Blueprint $table) {
            $table->foreignId('server_id')->constrained('monitoring_servers')->cascadeOnDelete();
            $table->dateTime('hour');
            $table->foreignId('company_id')->constrained();
            foreach (['cpu', 'cpu_max', 'memory', 'memory_max', 'swap', 'swap_max', 'disk', 'disk_max',
                'load1', 'load1_max', 'rx_bps', 'tx_bps'] as $column) {
                $table->double($column);
            }
            $table->unsignedInteger('samples');

            $table->primary(['server_id', 'hour']);
            $table->index('hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_metrics_hourly');
        Schema::dropIfExists('monitoring_metrics_raw');
        Schema::dropIfExists('monitoring_server_snapshots');
        Schema::dropIfExists('monitoring_servers');
    }
};
