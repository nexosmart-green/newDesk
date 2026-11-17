<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-14.06: one weekly summary per company and week (the unique key makes sending idempotent), with what it
 * cost when the AI wrote it (it counts toward the monthly cap, RF-14.09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_weekly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->date('week_from');
            $table->date('week_to');
            $table->text('content')->nullable();                 // null while it is being written
            $table->string('written_by', 16)->nullable();        // composing | ai | template
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->json('channels')->nullable();                // per channel: {reached: n} or {error: …} (retried)
            $table->timestamps();

            $table->unique(['company_id', 'week_from']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_weekly_summaries');
    }
};
