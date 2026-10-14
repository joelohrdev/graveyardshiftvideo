<?php

namespace Database\Factories;

use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Copy>
 */
class CopyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'movie_id' => Movie::factory(),
            'format' => fake()->randomElement(MediaFormat::cases()),
            'edition' => null,
            'condition' => fake()->randomElement(['Mint', 'Good', 'Worn']),
            'acquired_on' => fake()->dateTimeBetween('-10 years'),
            'notes' => null,
        ];
    }
}
