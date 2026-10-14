<?php

namespace App\Jobs;

use App\Enums\CreditRole;
use App\Models\Franchise;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Movie;
use App\Models\Person;
use App\Services\Tmdb\TmdbClient;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;

/**
 * Pulls a movie's full TMDB record (runtime, genres, keywords, directors, top cast) into the local catalog.
 */
class SyncMovieDetails implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Billed cast beyond this are rarely interesting on a VHS box, so they are not stored.
     */
    public const int CastLimit = 10;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * A movie deleted while this job waited in the queue needs no sync.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Movie $movie) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tmdb')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(TmdbClient $tmdb): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $details = $tmdb->movieDetails($this->movie->tmdb_id);

        if ($details === null) {
            $this->forgetRemovedMovie();

            return;
        }

        $franchise = $this->franchiseFor($details['belongs_to_collection'] ?? null);

        DB::transaction(function () use ($details, $franchise): void {
            $this->movie->update([...$this->movieAttributes($details), 'franchise_id' => $franchise?->id]);
            $this->movie->genres()->sync($this->upsertTaxonomy(Genre::class, $details['genres'] ?? []));
            $this->movie->keywords()->sync($this->upsertTaxonomy(Keyword::class, $details['keywords']['keywords'] ?? []));
            $this->syncCredits($details['credits'] ?? []);
        });

        if ($franchise !== null && ($franchise->parts_synced_at === null || $franchise->parts_synced_at->lt(now()->subMonths(5)))) {
            SyncFranchiseParts::dispatch($franchise);
        }
    }

    /**
     * The series this movie belongs to, created on first sight.
     *
     * @param  array{id?: int, name?: string, poster_path?: string|null, backdrop_path?: string|null}|null  $collection
     */
    protected function franchiseFor(?array $collection): ?Franchise
    {
        if (! isset($collection['id'], $collection['name'])) {
            return null;
        }

        return Franchise::query()->firstOrCreate(['tmdb_id' => $collection['id']], [
            'name' => $collection['name'],
            'poster_path' => $collection['poster_path'] ?? null,
            'backdrop_path' => $collection['backdrop_path'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    protected function movieAttributes(array $details): array
    {
        $releaseDate = filled($details['release_date'] ?? null) ? $details['release_date'] : null;

        return [
            'title' => $details['title'] ?? $this->movie->title,
            'original_title' => $details['original_title'] ?? null,
            'overview' => filled($details['overview'] ?? null) ? $details['overview'] : null,
            'release_date' => $releaseDate,
            'release_year' => $releaseDate !== null ? (int) substr($releaseDate, 0, 4) : $this->movie->release_year,
            'runtime' => filled($details['runtime'] ?? null) ? (int) $details['runtime'] : null,
            'poster_path' => $details['poster_path'] ?? null,
            'backdrop_path' => $details['backdrop_path'] ?? null,
            'original_language' => $details['original_language'] ?? null,
            'vote_average' => (float) ($details['vote_average'] ?? 0),
            'vote_count' => (int) ($details['vote_count'] ?? 0),
            'popularity' => (float) ($details['popularity'] ?? 0),
            'details_synced_at' => now(),
        ];
    }

    /**
     * Upsert TMDB `{id, name}` pairs into a genre/keyword table and return the local ids.
     *
     * @param  class-string<Genre|Keyword>  $model
     * @param  list<array{id: int, name: string}>  $items
     * @return array<int, mixed>
     */
    protected function upsertTaxonomy(string $model, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $model::upsert(
            array_map(fn (array $item): array => [
                'tmdb_id' => $item['id'],
                'name' => $item['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $items),
            ['tmdb_id'],
            ['name', 'updated_at'],
        );

        return $model::query()->whereIn('tmdb_id', array_column($items, 'id'))->pluck('id')->all();
    }

    /**
     * @param  array{cast?: list<array<string, mixed>>, crew?: list<array<string, mixed>>}  $credits
     */
    protected function syncCredits(array $credits): void
    {
        $directors = collect($credits['crew'] ?? [])
            ->where('job', 'Director')
            ->unique('id')
            ->values();

        $cast = collect($credits['cast'] ?? [])
            ->sortBy('order')
            ->unique('id')
            ->take(self::CastLimit)
            ->values();

        $everyone = $directors->concat($cast)->unique('id');

        if ($everyone->isNotEmpty()) {
            Person::upsert(
                $everyone->map(fn (array $person): array => [
                    'tmdb_id' => $person['id'],
                    'name' => $person['name'],
                    'profile_path' => $person['profile_path'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
                ['tmdb_id'],
                ['name', 'profile_path', 'updated_at'],
            );
        }

        $personIds = Person::query()->whereIn('tmdb_id', $everyone->pluck('id'))->pluck('id', 'tmdb_id');

        DB::table('credits')->where('movie_id', $this->movie->id)->delete();

        DB::table('credits')->insert([
            ...$directors->map(fn (array $person, int $index): array => [
                'movie_id' => $this->movie->id,
                'person_id' => $personIds[$person['id']],
                'role' => CreditRole::Director->value,
                'character' => null,
                'order' => $index,
            ])->all(),
            ...$cast->map(fn (array $person, int $index): array => [
                'movie_id' => $this->movie->id,
                'person_id' => $personIds[$person['id']],
                'role' => CreditRole::Cast->value,
                'character' => filled($person['character'] ?? null) ? $person['character'] : null,
                'order' => $index,
            ])->all(),
        ]);
    }

    /**
     * TMDB deleted the movie, so drop our cached copy unless it is on the shelf or the want list.
     */
    protected function forgetRemovedMovie(): void
    {
        if ($this->movie->copies()->exists() || $this->movie->watches()->exists()) {
            $this->movie->update(['details_synced_at' => now()]);

            return;
        }

        $this->movie->delete();
    }
}
