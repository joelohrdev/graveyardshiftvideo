<?php

namespace Database\Factories;

use App\Models\PriceSnapshot;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceSnapshot>
 */
class PriceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'watch_id' => Watch::factory(),
            'checked_on' => today(),
            'listing_count' => fake()->numberBetween(1, 40),
            'lowest_price' => $low = fake()->randomFloat(2, 3, 30),
            'median_price' => $low + 5,
            'highest_price' => $low + 20,
            'lowest_listing_url' => 'https://www.ebay.com/itm/'.fake()->numerify('############'),
            'lowest_listing_title' => fake()->sentence(4),
        ];
    }
}
