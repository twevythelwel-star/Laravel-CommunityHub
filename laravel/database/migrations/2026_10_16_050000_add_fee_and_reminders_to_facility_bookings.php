<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->unsignedInteger('fee_minor')->default(0)->after('capacity');
        });

        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->unsignedInteger('fee_minor')->default(0)->after('guests');
            $table->foreignId('invoice_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->timestamp('reminded_at')->nullable()->after('cancelled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn(['fee_minor', 'reminded_at']);
        });

        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn('fee_minor');
        });
    }
};
