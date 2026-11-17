<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-05.19: one clock per person and day, not tied to a task; split into time entries when stopped.
        Schema::create('day_clocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            // Explicit default: without it MariaDB (explicit_defaults_for_timestamp=OFF) adds ON UPDATE CURRENT_TIMESTAMP
            // to a table's first TIMESTAMP and stopping the day would rewrite its start.
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();  // NULL = running
            $table->timestamp('split_at')->nullable();  // NULL = running or waiting to be split
            $table->timestamps();

            // Invariant 7: at most one day not yet split per user (running or pending).
            $table->unsignedBigInteger('unsplit_user_id')->nullable()->storedAs('IF(`split_at` IS NULL, `user_id`, NULL)');
            $table->unique('unsplit_user_id');

            $table->index(['ended_at']);
        });

        // R-6: a ▶ running when a day split overdrew its project keeps its whole time.
        Schema::table('time_entries', function (Blueprint $table) {
            $table->boolean('uncapped')->default(false)->after('comment_pending');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('uncapped');
        });
        Schema::dropIfExists('day_clocks');
    }
};
