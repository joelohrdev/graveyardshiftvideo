<?php

namespace App\Http\Controllers;

use App\Actions\RemoveMovies;
use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use App\Http\Requests\DestroyMoviesRequest;
use App\Http\Resources\CopyResource;
use App\Http\Resources\MovieCardResource;
use App\Http\Resources\WatchResource;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MovieController extends Controller
{
    /**
     * Sort options for the aisles, keyed by the value the UI sends.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array Sorts = [
        'popular' => ['popularity', 'desc'],
        'title' => ['title', 'asc'],
        'newest' => ['release_date', 'desc'],
        'oldest' => ['release_date', 'asc'],
        'rating' => ['vote_average', 'desc'],
    ];

    /**
     * Browse the whole catalog as shelves of tapes.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'owned' => ['nullable', 'boolean'],
            'format' => ['nullable', Rule::enum(MediaFormat::class)],
            'decade' => ['nullable', 'integer', 'min:1890', 'max:2100'],
            'genre' => ['nullable', 'integer'],
            'subgenre' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(array_keys(self::Sorts))],
        ]);

        $user = $request->user();
        [$sortColumn, $sortDirection] = self::Sorts[$filters['sort'] ?? 'popular'];

        $movies = Movie::query()
            ->withCopiesOf($user)
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->boolean('owned'), fn (Builder $query) => $query->ownedBy($user))
            ->when($filters['format'] ?? null, fn (Builder $query, string $format) => $query->whereHas(
                'copies',
                fn (Builder $copies) => $copies->whereBelongsTo($user)->where('format', $format),
            ))
            ->when($filters['decade'] ?? null, fn (Builder $query, int|string $decade) => $query->whereBetween('release_year', [(int) $decade, (int) $decade + 9]))
            ->when($filters['genre'] ?? null, fn (Builder $query, int|string $genre) => $query->whereHas('genres', fn (Builder $genres) => $genres->whereKey($genre)))
            ->when($filters['subgenre'] ?? null, fn (Builder $query, int|string $keyword) => $query->whereHas('keywords', fn (Builder $keywords) => $keywords->whereKey($keyword)))
            ->when($sortColumn === 'vote_average', fn (Builder $query) => $query->where('vote_count', '>=', 50))
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id')
            ->paginate(48)
            ->withQueryString();

        return Inertia::render('aisles/index', [
            'movies' => Inertia::scroll(MovieCardResource::collection($movies)),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'owned' => $request->boolean('owned'),
                'format' => $filters['format'] ?? null,
                'decade' => isset($filters['decade']) ? (int) $filters['decade'] : null,
                'genre' => isset($filters['genre']) ? (int) $filters['genre'] : null,
                'subgenre' => isset($filters['subgenre']) ? (int) $filters['subgenre'] : null,
                'sort' => $filters['sort'] ?? 'popular',
            ],
            'facets' => Inertia::once(fn (): array => $this->facets()),
            'catalogSize' => Movie::query()->count(),
        ]);
    }

    /**
     * Remove a batch of movies from the store so they stay out of future imports.
     */
    public function destroyMany(DestroyMoviesRequest $request, RemoveMovies $removeMovies): RedirectResponse
    {
        ['removed' => $removed, 'kept' => $kept] = $removeMovies->handle($request->movieIds());

        $message = $removed === 1 ? 'Removed 1 movie.' : "Removed {$removed} movies.";

        if ($kept > 0) {
            $message .= $kept === 1
                ? ' Kept 1 that is on your shelf or want list.'
                : " Kept {$kept} that are on your shelf or want list.";
        }

        Inertia::flash('toast', ['type' => $removed > 0 ? 'success' : 'info', 'message' => $message]);

        return back();
    }

    /**
     * The tape box: details, credits, my copies and my want-list entries for this movie.
     */
    public function show(Request $request, Movie $movie): Response
    {
        $user = $request->user();

        $movie->load([
            'directors',
            'cast',
            'genres',
            'keywords',
            'copies' => fn (HasMany $copies) => $copies->whereBelongsTo($user)->latest(),
            'watches' => fn (HasMany $watches) => $watches->whereBelongsTo($user)->with([
                'snapshots' => fn (HasMany $snapshots) => $snapshots->where('checked_on', '>=', now()->subDays(90)),
            ]),
        ]);

        return Inertia::render('movies/show', [
            'movie' => [
                'id' => $movie->id,
                'tmdb_id' => $movie->tmdb_id,
                'title' => $movie->title,
                'original_title' => $movie->original_title,
                'overview' => $movie->overview,
                'release_date' => $movie->release_date?->toDateString(),
                'year' => $movie->release_year,
                'runtime' => $movie->runtime,
                'poster_path' => $movie->poster_path,
                'backdrop_path' => $movie->backdrop_path,
                'vote_average' => $movie->vote_average,
                'vote_count' => $movie->vote_count,
                'directors' => $movie->directors->map(fn (Person $person): array => [
                    'id' => $person->id, 'name' => $person->name,
                ])->all(),
                'cast' => $movie->cast->map(fn (Person $person): array => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'character' => $person->pivot->character,
                    'profile_path' => $person->profile_path,
                ])->all(),
                'genres' => $movie->genres->map(fn (Genre $genre): array => ['id' => $genre->id, 'name' => $genre->name])->all(),
                'keywords' => $movie->keywords->map(fn (Keyword $keyword): array => ['id' => $keyword->id, 'name' => $keyword->name])->all(),
            ],
            'copies' => CopyResource::collection($movie->copies)->resolve(),
            'watches' => WatchResource::collection($movie->watches)->resolve(),
            'formats' => MediaFormat::options(),
            'conditions' => array_map(
                fn (ListingCondition $condition): array => ['value' => $condition->value, 'label' => $condition->label()],
                ListingCondition::cases(),
            ),
        ]);
    }

    /**
     * Filter choices for the aisles. Subgenres are the most common TMDB keywords in the catalog.
     *
     * @return array{genres: array<int, array{id: int, name: string}>, subgenres: array<int, array{id: int, name: string}>, decades: list<int>, formats: list<array{value: string, label: string}>}
     */
    protected function facets(): array
    {
        return Cache::remember('aisles:facets', now()->addHour(), fn (): array => [
            'genres' => Genre::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Genre $genre): array => ['id' => $genre->id, 'name' => $genre->name])
                ->all(),
            'subgenres' => Keyword::query()
                ->withCount('movies')
                ->orderByDesc('movies_count')
                ->limit(40)
                ->get(['id', 'name'])
                ->map(fn (Keyword $keyword): array => ['id' => $keyword->id, 'name' => $keyword->name])
                ->sortBy('name')
                ->values()
                ->all(),
            'decades' => range(
                (int) floor(((int) Movie::query()->min('release_year') ?: 1920) / 10) * 10,
                (int) floor(now()->year / 10) * 10,
                10,
            ),
            'formats' => MediaFormat::options(),
        ]);
    }
}
