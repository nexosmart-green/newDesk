<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-09: per-user choice of which events also arrive by e-mail. Absent row = default (on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('email');

            $table->primary(['user_id', 'event']);
        });

        // Daily due-date reminders are sent once per task, person and day.
        Schema::create('task_due_reminders', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('sent_on');

            $table->primary(['task_id', 'user_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_due_reminders');
        Schema::dropIfExists('notification_preferences');
    }
};
