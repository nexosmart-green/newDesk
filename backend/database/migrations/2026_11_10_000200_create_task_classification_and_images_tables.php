<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desk parity (2026-11-10): per-company task categories and labels, and images pasted into task descriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 80);
            $table->string('color', 7); // #rrggbb
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('task_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('task_labels')->cascadeOnDelete();

            $table->unique(['task_id', 'label_id']);
            $table->index('label_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('status_id')->constrained('task_categories')->nullOnDelete();
        });

        Schema::create('task_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('path');
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_images');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('task_label');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_categories');
    }
};
