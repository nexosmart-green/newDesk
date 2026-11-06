<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-06: shift templates, per-day assignments (local date + local times, company timezone),
 * holidays and the personal iCal feed token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 60);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('color', 7);
            $table->string('mode', 10); // office | remote | hybrid
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('date');
            $table->foreignId('shift_template_id')->nullable()->constrained()->nullOnDelete();
            // Copied from the template so editing a template never rewrites past schedules.
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('mode', 10);
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'date', 'starts_at']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->date('date');
            $table->string('name', 120);
            $table->string('scope', 10); // national | company
            $table->timestamps();

            $table->unique(['company_id', 'date', 'name']);
        });

        Schema::create('calendar_feed_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_tokens');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shift_templates');
    }
};
