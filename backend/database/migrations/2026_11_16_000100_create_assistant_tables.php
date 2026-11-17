<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-14: assistant conversations (one Claude Code session each), their messages (with the estimated cost of each
 * turn), the write proposals waiting for a human (RF-14.02) and the report files it generates (RF-14.05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);                        // web | discord
            $table->string('title', 120)->nullable();
            $table->string('claude_session_id', 64)->nullable(); // `claude --resume`
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 16);                          // user | assistant
            $table->longText('content')->nullable();
            $table->string('status', 16);                        // pending | running | done | failed
            $table->string('error', 500)->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('access_token_id')->nullable(); // the ephemeral token of the turn (revoked after it)
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['company_id', 'created_at']);         // monthly spend (RF-14.09)
            $table->index('access_token_id');
        });

        Schema::create('assistant_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('assistant_messages')->cascadeOnDelete();
            $table->string('tool', 64);
            $table->json('arguments');
            $table->text('summary');
            $table->string('status', 16);                        // pending | confirmed | cancelled | failed | expired
            $table->json('result')->nullable();
            $table->string('error', 500)->nullable();
            $table->string('resolved_channel', 16)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['message_id', 'id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('assistant_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('assistant_messages')->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('format', 8);                         // xlsx | pdf
            $table->unsignedInteger('size');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_files');
        Schema::dropIfExists('assistant_proposals');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
