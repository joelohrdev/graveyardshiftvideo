<?php

namespace App\Http\Controllers;

use App\Enums\MediaFormat;
use App\Http\Requests\ImportMovieRequest;
use App\Jobs\SyncMovieDetails;
use App\Models\Copy;
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

        $year = isset($validated['year']) ? (int) $validated['year'] : null;

        try {
            $results = $tmdb->searchMovies($validated['q'], $year);

            // "Lucio Fulci's The Beyond": TMDB titles rarely carry the possessive credit, so retry without it.
            $withoutCredit = $this->withoutPossessiveCredit($validated['q']);

            if ($results === [] && $withoutCredit !== null) {
                $results = $tmdb->searchMovies($withoutCredit, $year);
            }
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'TMDB did not answer. Try again in a moment.'], 502);
        }

        $inStore = Movie::query()
            ->whereIn('tmdb_id', array_column($results, 'id'))
            ->withCopiesOf($request->user())
            ->get(['id', 'tmdb_id'])
            ->keyBy('tmdb_id');

        return response()->json([
            'results' => array_map(fn (array $result): array => [
                'tmdb_id' => (int) $result['id'],
                'title' => (string) $result['title'],
                'year' => filled($result['release_date'] ?? null) ? (int) substr($result['release_date'], 0, 4) : null,
                'poster_path' => $result['poster_path'] ?? null,
                'overview' => $result['overview'] ?? null,
                'movie_id' => $inStore->get($result['id'])?->id,
                'owned_formats' => $inStore->get($result['id'])?->copies
                    ->map(fn (Copy $copy): string => $copy->format->value)
                    ->unique()
                    ->values()
                    ->all() ?? [],
            ], array_slice($results, 0, 12)),
        ]);
    }

    /**
     * The title part of a "Director's Title" search, or null when there's no possessive credit.
     */
    protected function withoutPossessiveCredit(string $query): ?string
    {
        if (! preg_match("/^.+?['’]s\\s+(.{2,})$/u", trim($query), $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * Pull the chosen movie's full TMDB record into the store. With a format, also put
     * a copy on my shelf and stay in the search; without one, open the movie.
     */
    public function store(ImportMovieRequest $request): RedirectResponse
    {
        $tmdbId = (int) $request->validated('tmdb_id');
        $format = $request->validated('format') !== null ? MediaFormat::from($request->validated('format')) : null;

        $movie = Movie::query()->firstWhere('tmdb_id', $tmdbId)
            ?? $this->import($tmdbId, $request->validated('title'));

        if ($movie === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'TMDB no longer has that movie.']);

            return back();
        }

        if ($format === null) {
            if ($movie->wasRecentlyCreated) {
                Inertia::flash('toast', ['type' => 'success', 'message' => "{$movie->title} added to the store."]);
            }

            return to_route('movies.show', $movie);
        }

        $request->user()->copies()->create(['movie_id' => $movie->id, 'format' => $format]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$format->label()} copy of {$movie->title} added to your shelf."]);

        return back();
    }

    /**
     * Create the movie and fetch its details now. Returns null if TMDB has since deleted it.
     */
    protected function import(int $tmdbId, string $title): ?Movie
    {
        // Adding a movie by hand overrides an earlier removal.
        ExcludedMovie::query()->where('tmdb_id', $tmdbId)->delete();

        $movie = Movie::query()->create(['tmdb_id' => $tmdbId, 'title' => $title]);

        try {
            SyncMovieDetails::dispatchSync($movie);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Added, but TMDB details will fill in on the next refresh.']);

            return $movie;
        }

        return $movie->fresh();
    }
}
