<?php

namespace App\Actions;

use App\Jobs\ImportDiscoverPage;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Queues a TMDB discover import for one genre across a range of release years.
 */
class StartGenreImport
{
    public const string BatchCacheKey = 'tmdb-import:batch-id';

    /**
     * The first year TMDB has films for; earlier years return nothing.
     */
    public const int EarliestYear = 1895;

    public function handle(int $genreId, ?int $fromYear = null, ?int $toYear = null, bool $refresh = false): Batch
    {
        $fromYear ??= self::EarliestYear;
        $toYear ??= (int) now()->year;

        $batch = Bus::batch(array_map(
            fn (int $year): ImportDiscoverPage => new ImportDiscoverPage($genreId, $year, 1, $refresh),
            range($toYear, $fromYear),
        ))
            ->name("TMDB genre {$genreId} import {$fromYear}–{$toYear}")
            ->allowFailures()
            ->dispatch();

        Cache::forever(self::BatchCacheKey, $batch->id);

        return $batch;
    }

    /**
     * The most recently started import, if its batch record still exists.
     */
    public function latest(): ?Batch
    {
        $batchId = Cache::get(self::BatchCacheKey);

        return $batchId !== null ? Bus::findBatch($batchId) : null;
    }
}
