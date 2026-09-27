<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

/**
 * The amenities amenity-booking-dialog.tsx used to hard-code as
 * DEFAULT_AMENITIES, with the same names, capacities and hours.
 */
class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['name' => 'Community Center & Ballroom', 'category' => 'Community Center', 'max_guests' => 60, 'opens_at' => '08:00', 'closes_at' => '22:00'],
            ['name' => 'West Tennis & Pickleball Courts', 'category' => 'Park', 'max_guests' => 8, 'opens_at' => '06:00', 'closes_at' => '21:00'],
            ['name' => 'Central Pool Pavilion & Deck', 'category' => 'Community Center', 'max_guests' => 30, 'opens_at' => '07:00', 'closes_at' => '20:00'],
            ['name' => 'Evergreen BBQ Grills & Picnic Gazebo', 'category' => 'Park', 'max_guests' => 25, 'opens_at' => '10:00', 'closes_at' => '21:00'],
            ['name' => 'Executive Meeting Room & Lounge', 'category' => 'Community Center', 'max_guests' => 16, 'opens_at' => '08:00', 'closes_at' => '20:00'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::updateOrCreate(['name' => $amenity['name']], $amenity);
        }
    }
}
