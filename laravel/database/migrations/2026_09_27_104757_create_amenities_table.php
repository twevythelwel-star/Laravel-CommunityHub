<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Bookable shared facilities: the clubhouse, courts, pool and so on.
 |
 | Replaces DEFAULT_AMENITIES in amenity-booking-dialog.tsx. Capacity and
 | opening hours live here so the server, not the browser, decides whether a
 | booking fits. landmark_id optionally ties an amenity to its pin on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landmark_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('max_guests');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
