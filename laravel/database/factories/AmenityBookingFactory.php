<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmenityBooking>
 */
class AmenityBookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amenity_id' => Amenity::factory(),
            'user_id' => User::factory(),
            'booked_on' => now()->addDays(3)->toDateString(),
            'slot' => 'afternoon',
            'guests' => 4,
        ];
    }
}
