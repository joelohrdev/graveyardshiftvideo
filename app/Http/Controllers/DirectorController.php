<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DirectorController extends Controller
{
    /**
     * Directors' Row: everyone who directed something in the catalog, with how much of their work I own.
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

        $directors = Person::query()
            ->directors()
            ->withCount([
                'directed',
                'directed as owned_count' => $ownedByUser,
            ])
            ->unless($showAll, fn (Builder $query) => $query->whereHas('directed', $ownedByUser))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('owned_count')
            ->orderByDesc('directed_count')
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString()
            ->through(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'profile_path' => $person->profile_path,
                'directed_count' => $person->directed_count,
                'owned_count' => $person->owned_count,
            ]);

        return Inertia::render('directors/index', [
            'directors' => Inertia::scroll($directors),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'all' => $showAll,
            ],
        ]);
    }
}
