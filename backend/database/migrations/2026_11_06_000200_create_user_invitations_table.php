<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-01.01: no public sign-up. An Admin invites; the invitee sets their own password.
     */
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users');
            $table->char('token_hash', 64)->unique(); // sha256 of the emailed token; the raw token is never stored
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
