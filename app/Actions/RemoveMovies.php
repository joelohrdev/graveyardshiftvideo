<?php

namespace App\Actions;

use App\Models\ExcludedMovie;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Takes movies out of the store for good: deletes them and remembers their TMDB
 * ids so later imports skip them. Movies someone owns or is pricing are kept.
 */
class RemoveMovies
{
    /**
     * @param  list<int>  $movieIds
     * @return array{removed: int, kept: int}
     */
    public function handle(array $movieIds): array
    {
        $movies = Movie::query()->whereKey($movieIds)->get(['id', 'tmdb_id', 'title']);

        $keptIds = Movie::query()
            ->whereKey($movies->modelKeys())
            ->where(fn (Builder $query) => $query->whereHas('copies')->orWhereHas('watches'))
            ->pluck('id');

        $removable = $movies->whereNotIn('id', $keptIds);

        if ($removable->isNotEmpty()) {
            DB::transaction(function () use ($removable): void {
                ExcludedMovie::upsert(
                    $removable->map(fn (Movie $movie): array => [
                        'tmdb_id' => $movie->tmdb_id,
                        'title' => $movie->title,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->values()->all(),
                    ['tmdb_id'],
                    ['title', 'updated_at'],
                );

                Movie::query()->whereKey($removable->modelKeys())->delete();
            });
        }

        return ['removed' => $removable->count(), 'kept' => $keptIds->count()];
    }
}
