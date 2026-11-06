<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-02 clients and RF-03 projects + members. The hour bag (ledger) arrives with RF-05 (E4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('legal_name', 150);
            $table->string('trade_name', 150)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('client_id')->nullable()->constrained();
            $table->string('name', 150);
            $table->string('code_prefix', 10);
            $table->text('description')->nullable();
            $table->string('type', 16);   // bag | internal (P-05)
            $table->string('status', 16)->default('active');
            $table->foreignId('leader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Cache of closed time entries, kept in the same transaction that closes them (E4).
            $table->unsignedBigInteger('consumed_seconds')->default(0);
            // Last task number issued; incremented under a row lock (CA-04.01).
            $table->unsignedInteger('task_sequence')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code_prefix']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->timestamp('added_at')->useCurrent();

            $table->primary(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects');
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id', 'created_at']);
        });
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
