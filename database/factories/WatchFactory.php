<?php

namespace Database\Factories;

use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use App\Models\Movie;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Watch>
 */
class WatchFactory extends Factory
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
            'format' => MediaFormat::Vhs,
            'condition' => ListingCondition::Any,
            'target_price' => null,
            'active' => true,
        ];
    }
}
