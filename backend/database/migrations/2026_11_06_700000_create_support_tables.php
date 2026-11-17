<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-13.08: monitoreo's portal_reports, portal_report_messages, portal_report_attachments and
     * report_roadmap (migrations 00003, 00005..00007, 00011, 00017), same shapes plus company_id. Ids
     * are kept on import (CA-13.23). Times have microseconds, as in monitoreo: the read marks compare
     * a message with the last time each side opened the chat, often within the same second.
     */
    public function up(): void
    {
        Schema::create('support_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email'); // kept when the user is deleted
            $table->string('part', 8)->nullable(); // web, app or cms (D-045); null = "No sé"
            $table->string('urgency', 16);
            $table->text('description');
            $table->string('status', 16)->default('received');
            $table->string('screenshot_path')->nullable(); // on the private disk, never public
            $table->string('screenshot_type', 32)->nullable();
            $table->unsignedInteger('screenshot_size')->default(0); // the client's upload quota (D-090)
            $table->longText('live_check');
            $table->longText('open_issues');
            $table->longText('recent_errors')->nullable();
            $table->longText('browser');
            $table->longText('server_snapshot')->nullable(); // the project's servers when reported (D-050)
            $table->string('ip', 64);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('resolved_at', 6)->nullable();
            // Chat (D-049): the last message of each side and when each side last opened it.
            $table->dateTime('last_client_message_at', 6)->nullable();
            $table->dateTime('last_team_message_at', 6)->nullable();
            $table->dateTime('client_seen_at', 6)->nullable();
            $table->dateTime('team_seen_at', 6)->nullable();

            $table->index(['status', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('support_report_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('report_id')->constrained('support_reports')->cascadeOnDelete();
            $table->string('author_kind', 8); // client or team
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author'); // the address when written, kept if the user is deleted
            $table->text('body');
            $table->boolean('internal')->default(false); // team-only note (D-051)
            $table->dateTime('created_at', 6);

            $table->index(['report_id', 'created_at']);
        });

        // Chat images, next to the screenshots; the files go after 180 days (D-049).
        Schema::create('support_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('message_id')->constrained('support_report_messages')->cascadeOnDelete();
            $table->string('path');
            $table->string('content_type', 32);
            $table->unsignedInteger('size');
            $table->dateTime('created_at', 6);

            $table->index('created_at');
        });

        // Each developer's own ordered list of reports to fix (D-064, D-066).
        Schema::create('support_roadmap_items', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('support_reports')->cascadeOnDelete();
            $table->integer('position');
            $table->dateTime('created_at', 6);

            $table->primary(['user_id', 'report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_roadmap_items');
        Schema::dropIfExists('support_report_attachments');
        Schema::dropIfExists('support_report_messages');
        Schema::dropIfExists('support_reports');
    }
};
