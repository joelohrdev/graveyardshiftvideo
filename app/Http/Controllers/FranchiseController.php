<?php

namespace App\Http\Controllers;

use App\Http\Resources\MovieCardResource;
use App\Models\Franchise;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FranchiseController extends Controller
{
    /**
     * Every series in the store with how many parts I own, the ones I've started first.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'all' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $showAll = $request->boolean('all');
        $ownedByUser = fn (Builder $movies) => $movies->whereHas('copies', fn (Builder $copies) => $copies->whereBelongsTo($user));

        $franchises = Franchise::query()
            ->whereHas('movies')
            ->withCount(['movies', 'movies as owned_count' => $ownedByUser])
            ->unless($showAll, fn (Builder $query) => $query->whereHas('movies', $ownedByUser))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('owned_count')
            ->orderBy('name')
            ->paginate(48)
            ->withQueryString()
            ->through(fn (Franchise $franchise): array => [
                'id' => $franchise->id,
                'name' => $franchise->displayName(),
                'poster_path' => $franchise->poster_path,
                'movies_count' => $franchise->movies_count,
                'owned_count' => $franchise->owned_count,
            ]);

        return Inertia::render('franchises/index', [
            'franchises' => Inertia::scroll($franchises),
            'filters' => ['q' => $filters['q'] ?? '', 'all' => $showAll],
        ]);
    }

    /**
     * The checklist: every part in release order, with the ones I own lit up.
     */
    public function show(Request $request, Franchise $franchise): Response
    {
        $user = $request->user();

        $movies = $franchise->movies()
            ->withCopiesOf($user)
            ->orderByRaw('release_date is null')
            ->orderBy('release_date')
            ->get();

        $wantedIds = $user->watches()->active()->whereIn('movie_id', $movies->modelKeys())->pluck('movie_id');

        return Inertia::render('franchises/show', [
            'franchise' => [
                'id' => $franchise->id,
                'name' => $franchise->displayName(),
                'overview' => $franchise->overview,
                'poster_path' => $franchise->poster_path,
                'backdrop_path' => $franchise->backdrop_path,
            ],
            'movies' => MovieCardResource::collection($movies)->resolve(),
            'ownedCount' => $movies->filter(fn (Movie $movie): bool => $movie->copies->isNotEmpty())->count(),
            'wantedIds' => $wantedIds->values()->all(),
        ]);
    }
}
