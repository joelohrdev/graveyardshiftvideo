<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Enums\MediaFormat;
use App\Http\Resources\CopyResource;
use App\Http\Resources\MovieCardResource;
use App\Http\Resources\WatchResource;
use App\Models\Copy;
use App\Models\Movie;
use App\Models\Watch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * @var list<string>
     */
    public const array Groupings = ['all', 'director', 'decade', 'format', 'subgenre'];

    /**
     * A subgenre needs at least this many of my movies to earn its own shelf; the rest share "Everything else".
     */
    public const int MinimumSubgenreShelf = 2;

    /**
     * My shelves, grouped the way I want to browse them, plus the store's running totals.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate(['group' => ['nullable', Rule::in(self::Groupings)]]);
        $group = $validated['group'] ?? 'all';
        $user = $request->user();

        $movies = Movie::query()
            ->ownedBy($user)
            ->withCopiesOf($user)
            ->with(['directors', ...($group === 'subgenre' ? ['keywords'] : [])])
            ->orderBy('release_date')
            ->get();

        $copiesByFormat = $user->copies()->toBase()
            ->selectRaw('format, count(*) as total')
            ->groupBy('format')
            ->pluck('total', 'format');

        $hotDeals = $user->watches()
            ->active()
            ->with(['movie', 'snapshots' => fn (HasMany $snapshots) => $snapshots->where('checked_on', '>=', now()->subDays(30))])
            ->get()
            ->filter(fn (Watch $watch): bool => in_array($watch->dealStatus(), [DealStatus::Steal, DealStatus::GoodDeal], true))
            ->values();

        return Inertia::render('collection/index', [
            'group' => $group,
            'shelves' => $group === 'all' ? [] : $this->shelves($movies, $group),
            'allMovies' => $group === 'all'
                ? MovieCardResource::collection(
                    $movies->sortBy(fn (Movie $movie): string => mb_strtolower($movie->title))->values(),
                )->resolve()
                : [],
            'stats' => [
                'movies' => $movies->count(),
                'copies' => (int) $copiesByFormat->sum(),
                'directors' => $movies->flatMap->directors->unique('id')->count(),
                'formats' => collect(MediaFormat::cases())
                    ->map(fn (MediaFormat $format): array => [
                        'value' => $format->value,
                        'label' => $format->label(),
                        'count' => (int) ($copiesByFormat[$format->value] ?? 0),
                    ])
                    ->filter(fn (array $format): bool => $format['count'] > 0)
                    ->values()
                    ->all(),
            ],
            'recentCopies' => CopyResource::collection(
                $user->copies()->with('movie')->latest()->limit(8)->get(),
            )->resolve(),
            'hotDeals' => WatchResource::collection($hotDeals)->resolve(),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: array<int, mixed>}>
     */
    protected function shelves(EloquentCollection $movies, string $group): array
    {
        $shelves = match ($group) {
            'decade' => $this->byDecade($movies),
            'format' => $this->byFormat($movies),
            'subgenre' => $this->bySubgenre($movies),
            default => $this->byDirector($movies),
        };

        return array_map(fn (array $shelf): array => [
            ...$shelf,
            'movies' => MovieCardResource::collection($shelf['movies'])->resolve(),
        ], $shelves);
    }

    /**
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}
     */
    protected function shelf(string $key, string $label, EloquentCollection $movies, ?int $personId = null): array
    {
        return ['key' => $key, 'label' => $label, 'person_id' => $personId, 'movies' => $movies->values()];
    }

    /**
     * Fullest shelves first, then alphabetical.
     *
     * @param  array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>  $shelves
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>
     */
    protected function biggestFirst(array $shelves): array
    {
        usort($shelves, fn (array $a, array $b): int => [$b['movies']->count(), $a['label']] <=> [$a['movies']->count(), $b['label']]);

        return $shelves;
    }

    /**
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>
     */
    protected function byDirector(EloquentCollection $movies): array
    {
        $shelves = [];

        foreach ($movies->flatMap->directors->unique('id') as $director) {
            $shelves[] = $this->shelf(
                "director-{$director->id}",
                $director->name,
                $movies->filter(fn (Movie $movie): bool => $movie->directors->contains($director)),
                $director->id,
            );
        }

        $shelves = $this->biggestFirst($shelves);
        $undirected = $movies->filter(fn (Movie $movie): bool => $movie->directors->isEmpty());

        if ($undirected->isNotEmpty()) {
            $shelves[] = $this->shelf('director-unknown', 'Director unknown', $undirected);
        }

        return $shelves;
    }

    /**
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>
     */
    protected function byDecade(EloquentCollection $movies): array
    {
        $decades = $movies
            ->groupBy(fn (Movie $movie): string => $movie->release_year !== null ? (string) (intdiv($movie->release_year, 10) * 10) : 'unknown')
            ->sortKeys();

        $shelves = [];

        foreach ($decades as $decade => $decadeMovies) {
            $shelves[] = $this->shelf(
                "decade-{$decade}",
                $decade === 'unknown' ? 'Year unknown' : "{$decade}s",
                $movies->only($decadeMovies->modelKeys()),
            );
        }

        return $shelves;
    }

    /**
     * A movie I own in several formats shows up on each format's shelf.
     *
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>
     */
    protected function byFormat(EloquentCollection $movies): array
    {
        $shelves = [];

        foreach (MediaFormat::cases() as $format) {
            $formatMovies = $movies->filter(
                fn (Movie $movie): bool => $movie->copies->contains(fn (Copy $copy): bool => $copy->format === $format),
            );

            if ($formatMovies->isNotEmpty()) {
                $shelves[] = $this->shelf("format-{$format->value}", $format->label(), $formatMovies);
            }
        }

        return $shelves;
    }

    /**
     * @param  EloquentCollection<int, Movie>  $movies
     * @return array<int, array{key: string, label: string, person_id: int|null, movies: EloquentCollection<int, Movie>}>
     */
    protected function bySubgenre(EloquentCollection $movies): array
    {
        $shelves = [];

        foreach ($movies->flatMap->keywords->unique('id') as $keyword) {
            $keywordMovies = $movies->filter(fn (Movie $movie): bool => $movie->keywords->contains($keyword));

            if ($keywordMovies->count() >= self::MinimumSubgenreShelf) {
                $shelves[] = $this->shelf("subgenre-{$keyword->id}", $keyword->name, $keywordMovies);
            }
        }

        $shelves = $this->biggestFirst($shelves);
        $shelved = collect($shelves)->flatMap(fn (array $shelf): array => $shelf['movies']->modelKeys())->unique();
        $leftovers = $movies->reject(fn (Movie $movie): bool => $shelved->contains($movie->id));

        if ($leftovers->isNotEmpty()) {
            $shelves[] = $this->shelf('subgenre-other', 'Everything else', $leftovers);
        }

        return $shelves;
    }
}
