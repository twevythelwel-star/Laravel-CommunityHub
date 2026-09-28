<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A scan writes one entry (the pass checked out, ALLOW or DENY) and the
 | guard's decision writes another (CHECK_IN, CHECK_OUT, or a refusal). The
 | second now points at the first, so the kiosk can show the pair as one row
 | while the access log keeps both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_log_entries', function (Blueprint $table) {
            // Indexed: the kiosk asks "has this scan been confirmed?" per row.
            $table->foreignId('confirms_entry_id')->nullable()->after('scanned_by')
                ->index()->constrained('access_log_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_log_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirms_entry_id');
        });
    }
};
