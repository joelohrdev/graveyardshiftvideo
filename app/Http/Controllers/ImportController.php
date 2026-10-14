<?php

namespace App\Http\Controllers;

use App\Actions\StartGenreImport;
use App\Http\Requests\StartImportRequest;
use App\Models\Movie;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * The back room: restock the catalog from TMDB and watch the import run.
     */
    public function index(StartGenreImport $startGenreImport): Response
    {
        $batch = $startGenreImport->latest();

        return Inertia::render('back-room/index', [
            'tmdbConfigured' => filled(config('services.tmdb.token')),
            'import' => $batch === null ? null : [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'finished' => $batch->finished(),
                'cancelled' => $batch->cancelled(),
                'created_at' => $batch->createdAt->toIso8601String(),
                'finished_at' => $batch->finishedAt?->toIso8601String(),
            ],
            'catalog' => [
                'movies' => Movie::query()->count(),
                'synced' => Movie::query()->whereNotNull('details_synced_at')->count(),
                'oldest_sync' => Movie::query()->min('details_synced_at'),
            ],
            'earliestYear' => StartGenreImport::EarliestYear,
        ]);
    }

    /**
     * Queue a horror import for the requested range of years.
     */
    public function store(StartImportRequest $request, StartGenreImport $startGenreImport): RedirectResponse
    {
        if (blank(config('services.tmdb.token'))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Add TMDB_TOKEN to .env before restocking.']);

            return back();
        }

        $batch = $startGenreImport->handle(
            TmdbClient::HorrorGenreId,
            $request->integer('from_year') ?: null,
            $request->integer('to_year') ?: null,
            $request->boolean('refresh'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Restocking: {$batch->totalJobs} years queued."]);

        return to_route('imports.index');
    }
}
