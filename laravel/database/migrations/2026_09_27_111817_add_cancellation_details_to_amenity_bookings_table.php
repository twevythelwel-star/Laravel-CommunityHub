<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Who cancelled a booking and why. A booking cancelled by someone other than
 | the resident who made it is shown back to that resident with the reason,
 | and triggers an email/SMS per their notification preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenity_bookings', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('amenity_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancellation_reason');
        });
    }
};
