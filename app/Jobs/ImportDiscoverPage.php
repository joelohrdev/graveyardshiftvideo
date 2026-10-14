<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Services\Tmdb\TmdbClient;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Imports one page of TMDB's discover results for a genre and year.
 *
 * Page 1 fans out the remaining pages into the same batch, and every page queues
 * a details sync for the movies it touched that still need one.
 */
class ImportDiscoverPage implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $genreId,
        public int $year,
        public int $page = 1,
        public bool $refresh = false,
    ) {}

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

        $discovered = $tmdb->discoverByGenre($this->genreId, $this->year, $this->page);

        if ($this->page === 1 && $discovered['total_pages'] > 1) {
            $this->batch()?->add(array_map(
                fn (int $page): self => new self($this->genreId, $this->year, $page, $this->refresh),
                range(2, $discovered['total_pages']),
            ));
        }

        if ($discovered['results'] === []) {
            return;
        }

        $rows = array_map($this->movieRow(...), $discovered['results']);

        Movie::upsert($rows, ['tmdb_id'], array_keys(array_diff_key($rows[0], ['tmdb_id' => true, 'created_at' => true])));

        $needsDetails = Movie::query()
            ->whereIn('tmdb_id', array_column($rows, 'tmdb_id'))
            ->when(! $this->refresh, fn ($query) => $query->whereNull('details_synced_at'))
            ->get();

        $jobs = $needsDetails->map(fn (Movie $movie): SyncMovieDetails => new SyncMovieDetails($movie))->all();

        if ($jobs === []) {
            return;
        }

        if ($this->batch() !== null) {
            $this->batch()->add($jobs);
        } else {
            foreach ($jobs as $job) {
                dispatch($job);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function movieRow(array $result): array
    {
        $releaseDate = filled($result['release_date'] ?? null) ? $result['release_date'] : null;

        return [
            'tmdb_id' => (int) $result['id'],
            'title' => (string) $result['title'],
            'original_title' => $result['original_title'] ?? null,
            'overview' => filled($result['overview'] ?? null) ? $result['overview'] : null,
            'release_date' => $releaseDate,
            'release_year' => $releaseDate !== null ? (int) substr($releaseDate, 0, 4) : $this->year,
            'poster_path' => $result['poster_path'] ?? null,
            'backdrop_path' => $result['backdrop_path'] ?? null,
            'original_language' => $result['original_language'] ?? null,
            'vote_average' => (float) ($result['vote_average'] ?? 0),
            'vote_count' => (int) ($result['vote_count'] ?? 0),
            'popularity' => (float) ($result['popularity'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
