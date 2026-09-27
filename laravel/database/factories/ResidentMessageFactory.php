<?php

namespace Database\Factories;

use App\Models\ResidentMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentMessage>
 */
class ResidentMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'general',
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(12),
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
