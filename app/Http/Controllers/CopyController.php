<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCopyRequest;
use App\Http\Requests\UpdateCopyRequest;
use App\Models\Copy;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CopyController extends Controller
{
    /**
     * Put a new copy of the movie on my shelf.
     */
    public function store(StoreCopyRequest $request, Movie $movie): RedirectResponse
    {
        $copy = $request->user()->copies()->create([
            ...$request->validated(),
            'movie_id' => $movie->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$copy->format->label()} copy added to your shelf."]);

        return back();
    }

    /**
     * Update the details of one of my copies.
     */
    public function update(UpdateCopyRequest $request, Copy $copy): RedirectResponse
    {
        $copy->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Copy updated.']);

        return back();
    }

    /**
     * Take a copy off my shelf.
     */
    public function destroy(Copy $copy): RedirectResponse
    {
        Gate::authorize('delete', $copy);

        $copy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Copy removed.']);

        return back();
    }
}
