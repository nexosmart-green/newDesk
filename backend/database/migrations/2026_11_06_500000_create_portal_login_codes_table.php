<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-13.07: the client portal's mailed login codes (monitoreo's login_codes).
        Schema::create('portal_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // HMAC-SHA256 (hex) keyed with the app key: never the plain code nor the plain nonce.
            $table->char('code_hash', 64);
            // The requesting browser's portal_login cookie: the code only works there (D-082).
            $table->char('nonce_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->index(['user_id', 'created_at']); // codes per account and hour (D-081, D-087)
            $table->index(['user_id', 'nonce_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_login_codes');
    }
};
