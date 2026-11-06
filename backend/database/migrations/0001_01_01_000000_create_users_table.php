<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->char('currency', 3)->default('ARS');
            $table->string('timezone', 64)->default('America/Argentina/Buenos_Aires');
            // RF-11.03 hour rules; defaults live in Company::DEFAULT_SETTINGS.
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            // System roles (Admin) cannot be deleted nor lose permissions: avoids locking the owner out.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);

            $table->primary(['role_id', 'permission']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('role_id')->constrained();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null until the invitation is accepted (RF-01.01)
            $table->string('status', 16)->default('active')->index();
            $table->string('locale', 8)->default('es');
            $table->string('timezone', 64)->nullable(); // RNF-04 per-user override
            $table->string('avatar_path')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('companies');
    }
};
