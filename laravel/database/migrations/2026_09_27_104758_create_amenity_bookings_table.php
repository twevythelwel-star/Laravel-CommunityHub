<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A resident's reservation of an amenity for one time slot on one day.
 |
 | slot_key is "amenity:date:slot" while the booking is confirmed and NULL once
 | it is cancelled. Its unique index is what stops two residents holding the
 | same slot: a check-then-insert would let simultaneous requests both win.
 | Unique indexes allow many NULLs on SQLite, MySQL and Postgres alike, so
 | cancelled bookings keep their history without blocking the slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenity_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('booked_on');
            $table->string('slot', 16);
            $table->unsignedSmallInteger('guests');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('confirmed');
            $table->string('slot_key', 64)->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['amenity_id', 'booked_on']);
            $table->index(['user_id', 'booked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_bookings');
    }
};
