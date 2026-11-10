<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-04.12: when a task moves to another project it gets a new code; the old ones keep resolving to it
 * (they already circulate in commits, chats and MCP sessions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_code_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_code_aliases');
    }
};
