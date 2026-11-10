<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-04 tasks: company-level statuses, tasks with per-project correlative codes, assignees, comments and mentions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 60);
            $table->string('color', 7);          // #RRGGBB
            $table->string('kind', 16);          // open | blocked | closed — what the rules read
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained();
            $table->unsignedInteger('number');
            $table->string('code', 24);          // <prefix>-<number>, e.g. bhn-60
            $table->string('title', 255);
            $table->mediumText('description')->nullable(); // markdown
            $table->foreignId('status_id')->constrained('task_statuses');
            $table->string('priority', 16)->default('medium');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('estimate_seconds')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'number']); // CA-04.01 last line of defense
            $table->index(['company_id', 'code']);
            $table->index(['project_id', 'status_id']);
            $table->index('due_on');
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('assigned_at')->useCurrent();

            $table->primary(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
        });

        Schema::create('comment_mentions', function (Blueprint $table) {
            $table->foreignId('comment_id')->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('read_at')->nullable();

            $table->primary(['comment_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_statuses');
    }
};
