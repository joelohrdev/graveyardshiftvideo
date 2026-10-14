<?php

namespace App\Console\Commands;

use App\Actions\StartGenreImport;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tmdb:import {genre='.TmdbClient::HorrorGenreId.' : TMDB genre id (27 is Horror)} {--from= : First release year} {--to= : Last release year} {--refresh : Re-fetch details for movies already synced}')]
#[Description('Queue a TMDB import of every movie in a genre, one job per release year')]
class ImportTmdbGenre extends Command
{
    public function handle(StartGenreImport $startGenreImport): int
    {
        $from = $this->option('from') !== null ? (int) $this->option('from') : null;
        $to = $this->option('to') !== null ? (int) $this->option('to') : null;

        if ($from !== null && $to !== null && $from > $to) {
            $this->components->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $batch = $startGenreImport->handle((int) $this->argument('genre'), $from, $to, (bool) $this->option('refresh'));

        $this->components->info("Queued {$batch->totalJobs} year jobs in batch {$batch->id}. Keep a queue worker running to process them.");

        return self::SUCCESS;
    }
}
