<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-01 people: administrable catalogs, employee profile, skills and rate history.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['job_titles', 'areas', 'employment_types', 'skills'] as $catalog) {
            Schema::create($catalog, function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained();
                $table->string('name', 80);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('employee_code', 30)->nullable();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reports_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 30)->nullable();
            $table->text('about')->nullable();
            $table->date('hired_at')->nullable();
            $table->date('left_at')->nullable();
            $table->date('probation_ends_at')->nullable();
            // P-12: Admin-only fields, encrypted at rest (ciphertext needs TEXT).
            $table->text('cuil')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_code']);
        });

        Schema::create('user_skills', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level'); // 0..100

            $table->primary(['user_id', 'skill_id']);
            $table->index(['skill_id', 'level']);
        });

        // RF-01.11: a raise never rewrites past profitability. Admin only.
        Schema::create('hourly_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_rates');
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('employee_profiles');
        foreach (['skills', 'employment_types', 'areas', 'job_titles'] as $catalog) {
            Schema::dropIfExists($catalog);
        }
    }
};
