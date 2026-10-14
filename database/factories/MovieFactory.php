<?php

namespace Database\Factories;

use App\Models\Movie;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'title' => Str::title(rtrim(fake()->sentence(3), '.')),
            'overview' => fake()->paragraph(),
            'release_date' => $date = fake()->dateTimeBetween('1960-01-01', '2024-12-31'),
            'release_year' => (int) $date->format('Y'),
            'runtime' => fake()->numberBetween(75, 130),
            'poster_path' => '/'.Str::random(27).'.jpg',
            'original_language' => 'en',
            'vote_average' => fake()->randomFloat(1, 3, 9),
            'vote_count' => fake()->numberBetween(0, 5000),
            'popularity' => fake()->randomFloat(3, 0, 200),
        ];
    }

    /**
     * A movie released in a specific year.
     */
    public function releasedIn(int $year): static
    {
        return $this->state(fn (): array => [
            'release_date' => "{$year}-06-01",
            'release_year' => $year,
        ]);
    }

    /**
     * A movie whose TMDB details were fetched at the given time.
     */
    public function syncedAt(?CarbonInterface $syncedAt): static
    {
        return $this->state(fn (): array => ['details_synced_at' => $syncedAt]);
    }
}
