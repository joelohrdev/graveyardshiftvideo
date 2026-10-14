<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMovieRequest;
use App\Jobs\SyncMovieDetails;
use App\Models\ExcludedMovie;
use App\Models\Movie;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Adds single movies by hand, for titles the genre import missed.
 */
class MovieImportController extends Controller
{
    /**
     * Search TMDB by title (any genre) and flag results already in the store.
     */
    public function search(Request $request, TmdbClient $tmdb): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1880', 'max:2100'],
        ]);

        try {
            $results = $tmdb->searchMovies($validated['q'], isset($validated['year']) ? (int) $validated['year'] : null);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'TMDB did not answer. Try again in a moment.'], 502);
        }

        $inStore = Movie::query()
            ->whereIn('tmdb_id', array_column($results, 'id'))
            ->pluck('id', 'tmdb_id');

        return response()->json([
            'results' => array_map(fn (array $result): array => [
                'tmdb_id' => (int) $result['id'],
                'title' => (string) $result['title'],
                'year' => filled($result['release_date'] ?? null) ? (int) substr($result['release_date'], 0, 4) : null,
                'poster_path' => $result['poster_path'] ?? null,
                'overview' => $result['overview'] ?? null,
                'movie_id' => $inStore[$result['id']] ?? null,
            ], array_slice($results, 0, 12)),
        ]);
    }

    /**
     * Pull the chosen movie's full TMDB record into the store and open it.
     */
    public function store(ImportMovieRequest $request): RedirectResponse
    {
        $tmdbId = (int) $request->validated('tmdb_id');

        $existing = Movie::query()->firstWhere('tmdb_id', $tmdbId);

        if ($existing !== null) {
            return to_route('movies.show', $existing);
        }

        // Adding a movie by hand overrides an earlier removal.
        ExcludedMovie::query()->where('tmdb_id', $tmdbId)->delete();

        $movie = Movie::query()->create([
            'tmdb_id' => $tmdbId,
            'title' => $request->validated('title'),
        ]);

        try {
            SyncMovieDetails::dispatchSync($movie);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Added, but TMDB details will fill in on the next refresh.']);

            return to_route('movies.show', $movie);
        }

        if (Movie::query()->whereKey($movie->id)->doesntExist()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'TMDB no longer has that movie.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$movie->fresh()?->title} added to the store."]);

        return to_route('movies.show', $movie);
    }
}
