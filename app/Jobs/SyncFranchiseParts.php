<?php

namespace App\Jobs;

use App\Models\Franchise;
use App\Models\Movie;
use App\Services\Tmdb\CatalogWriter;
use App\Services\Tmdb\TmdbClient;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Brings every part of a movie series into the store, so its checklist can show
 * the entries you're missing even when they aren't tagged horror.
 */
class SyncFranchiseParts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * Several parts of one series finish syncing at once; only one parts fetch is needed.
     */
    public int $uniqueFor = 3600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Franchise $franchise) {}

    public function uniqueId(): string
    {
        return (string) $this->franchise->id;
    }

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
        $collection = $tmdb->collection($this->franchise->tmdb_id);

        if ($collection === null) {
            $this->franchise->delete();

            return;
        }

        $this->franchise->update([
            'name' => $collection['name'] ?? $this->franchise->name,
            'overview' => filled($collection['overview'] ?? null) ? $collection['overview'] : null,
            'poster_path' => $collection['poster_path'] ?? null,
            'backdrop_path' => $collection['backdrop_path'] ?? null,
            'parts_synced_at' => now(),
        ]);

        $tmdbIds = $catalog->upsertResults($collection['parts'] ?? []);

        if ($tmdbIds === []) {
            return;
        }

        Movie::query()->whereIn('tmdb_id', $tmdbIds)->update(['franchise_id' => $this->franchise->id]);

        Movie::query()
            ->whereIn('tmdb_id', $tmdbIds)
            ->whereNull('details_synced_at')
            ->get()
            ->each(fn (Movie $movie) => SyncMovieDetails::dispatch($movie));
    }
}
