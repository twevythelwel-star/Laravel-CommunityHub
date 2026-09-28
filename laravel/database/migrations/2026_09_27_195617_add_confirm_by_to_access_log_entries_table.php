<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | When a scan waiting on the guard lapses. Set only on scans that need a
 | decision, so a scan left unconfirmed past it can be shown as expired,
 | while entries that were final when written (refusals, manual check-ins)
 | never are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_log_entries', function (Blueprint $table) {
            $table->timestamp('confirm_by')->nullable()->after('confirms_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('access_log_entries', function (Blueprint $table) {
            $table->dropColumn('confirm_by');
        });
    }
};
