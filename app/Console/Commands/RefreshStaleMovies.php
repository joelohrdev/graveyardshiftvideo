<?php

namespace App\Console\Commands;

use App\Jobs\SyncFranchiseParts;
use App\Jobs\SyncMovieDetails;
use App\Models\Franchise;
use App\Models\Movie;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tmdb:refresh-stale')]
#[Description('Re-sync a daily slice of movies whose TMDB data is getting old (TMDB allows caching for at most 6 months)')]
class RefreshStaleMovies extends Command
{
    /**
     * Movies older than this are due for a refresh, leaving a month of slack before TMDB's 6-month cap.
     */
    public const int StaleAfterMonths = 5;

    /**
     * Spreading the whole catalog over this many days keeps the daily load small while finishing well inside the cap.
     */
    public const int RefreshWindowDays = 150;

    public function handle(): int
    {
        $dailyCap = max(1, (int) ceil(Movie::query()->count() / self::RefreshWindowDays));

        $stale = Movie::query()
            ->staleSince(now()->subMonths(self::StaleAfterMonths))
            ->orderByRaw('details_synced_at is not null')
            ->orderBy('details_synced_at')
            ->limit($dailyCap)
            ->get();

        $stale->each(fn (Movie $movie) => SyncMovieDetails::dispatch($movie));

        $staleFranchises = Franchise::query()
            ->where('parts_synced_at', '<', now()->subMonths(self::StaleAfterMonths))
            ->limit($dailyCap)
            ->get();

        $staleFranchises->each(fn (Franchise $franchise) => SyncFranchiseParts::dispatch($franchise));

        $this->components->info("Queued {$stale->count()} stale movies and {$staleFranchises->count()} franchises for a TMDB refresh (daily cap {$dailyCap}).");

        return self::SUCCESS;
    }
}
