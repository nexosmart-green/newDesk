<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB with explicit_defaults_for_timestamp=OFF gives a table's first TIMESTAMP an implicit
 * ON UPDATE CURRENT_TIMESTAMP: any update of a running entry (closing it, R-6's `uncapped`) rewrote its
 * start. An explicit default turns that off; MySQL 8 never added it, so there it changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->timestamp('started_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->timestamp('started_at')->change();
        });
    }
};
