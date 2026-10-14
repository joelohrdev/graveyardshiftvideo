<?php

namespace App\Http\Controllers;

use App\Enums\CreditRole;
use App\Http\Resources\MovieCardResource;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /**
     * A filmography shelf: what this person directed or starred in, with the tapes I own lit up.
     */
    public function show(Request $request, Person $person): Response
    {
        $movies = $person->movies()
            ->withCopiesOf($request->user())
            ->orderBy('release_date')
            ->get();

        $byRole = fn (CreditRole $role) => MovieCardResource::collection(
            $movies->filter(fn (Movie $movie): bool => $movie->pivot->role === $role)->values(),
        )->resolve();

        return Inertia::render('people/show', [
            'person' => [
                'id' => $person->id,
                'tmdb_id' => $person->tmdb_id,
                'name' => $person->name,
                'profile_path' => $person->profile_path,
            ],
            'directed' => $byRole(CreditRole::Director),
            'starred' => $byRole(CreditRole::Cast),
            'ownedCount' => $movies->filter(fn (Movie $movie): bool => $movie->copies->isNotEmpty())->unique('id')->count(),
        ]);
    }
}
