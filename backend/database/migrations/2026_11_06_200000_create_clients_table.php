<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-02.01. `name` is the trade name; the portal and monitoring show it (RF-13).
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 120);
            $table->string('legal_name', 160)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            // CA-02.01: a client with projects is inactivated, never deleted.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        // A user with client_id is a client (portal only, RF-13.07); NULL is the team.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('role_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::dropIfExists('clients');
    }
};
