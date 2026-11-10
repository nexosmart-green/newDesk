<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-05: hour bag ledger, extension requests and time entries (timers live here: ended_at NULL = running).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Cache of Σ hour_allocations, written in the same transaction as each allocation.
            $table->bigInteger('allocated_seconds')->default(0)->after('consumed_seconds');
            // Highest consumption alert already sent (0, 80, 100…) so RF-05.07 fires once per crossing.
            $table->unsignedTinyInteger('hour_alert_level')->default(0)->after('allocated_seconds');
        });

        // Insert-only ledger (invariant 5). Never updated nor deleted.
        Schema::create('hour_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->bigInteger('seconds');            // + loads hours, - corrects
            $table->string('reason', 255);
            $table->string('reference', 120)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('hour_extension_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->text('message')->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('allocation_id')->nullable()->constrained('hour_allocations');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('task_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('seconds')->nullable();     // set when closed; integer seconds (R-5)
            $table->string('source', 16);                       // timer | manual | migration
            $table->string('channel', 8)->default('web');       // web | mcp
            $table->text('comment')->nullable();                // RF-05.16: required (≥10) to close by hand
            $table->boolean('comment_pending')->default(false); // RF-05.17: automatic cuts
            $table->string('stop_reason', 24)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Invariant 1: at most one running timer per user. MySQL has no partial indexes,
            // so a generated column holds user_id only while running and is UNIQUE.
            $table->unsignedBigInteger('active_user_id')->nullable()->storedAs('IF(`ended_at` IS NULL AND `deleted_at` IS NULL, `user_id`, NULL)');
            $table->unique('active_user_id');

            $table->index(['project_id', 'ended_at']);
            $table->index(['user_id', 'started_at']);
            $table->index(['task_id']);
            $table->index(['user_id', 'comment_pending']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('hour_extension_requests');
        Schema::dropIfExists('hour_allocations');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['allocated_seconds', 'hour_alert_level']);
        });
    }
};
