<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-06.03: recurring shifts ("lun a vie, Completo, desde 01/12"). Each generated day remembers its rule
 * so ending or deleting the rule removes only the future days it created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('shift_template_id')->constrained();
            $table->json('weekdays'); // ISO 1 (Monday) .. 7
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('skip_holidays')->default(true);
            // Last local day already evaluated: days freed afterwards by hand are never refilled.
            $table->date('generated_until')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['company_id', 'user_id']);
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->foreignId('recurrence_id')->nullable()->after('shift_template_id')
                ->constrained('shift_recurrences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_id');
        });
        Schema::dropIfExists('shift_recurrences');
    }
};
