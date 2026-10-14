<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Services\Tmdb\CatalogWriter;
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

    public function handle(TmdbClient $tmdb, CatalogWriter $catalog): void
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

        $tmdbIds = $catalog->upsertResults($discovered['results'], $this->year);

        if ($tmdbIds === []) {
            return;
        }

        $needsDetails = Movie::query()
            ->whereIn('tmdb_id', $tmdbIds)
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
}
