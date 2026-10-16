<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility booking on the existing landmarks: a landmark can be bookable, with an optional
 * capacity, and residents book it for a date and time slot. Security gates are not bookable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->boolean('bookable')->default(false)->after('icon');
            $table->unsignedSmallInteger('capacity')->nullable()->after('bookable');
        });

        DB::table('landmarks')->where('category', '!=', 'Security Gate')->update(['bookable' => true]);

        Schema::create('facility_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('landmark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('booked_on');
            $table->string('slot', 16);
            $table->unsignedSmallInteger('guests');
            $table->string('notes', 500)->nullable();
            $table->string('status', 16)->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landmark_id', 'booked_on', 'slot', 'status']);
            $table->index(['user_id', 'booked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_bookings');

        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn(['bookable', 'capacity']);
        });
    }
};
