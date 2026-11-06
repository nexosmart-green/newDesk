<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable audit trail (RF-03.06, RNF-07, RF-12.06). Insert-only.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->nullableMorphs('subject');
            // Plain index for now; the FK to projects is added by the Projects module migration.
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('source', 16)->default('web'); // web | mcp | system
            $table->string('token_name')->nullable();     // MCP token used, when source = mcp
            $table->string('event', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
