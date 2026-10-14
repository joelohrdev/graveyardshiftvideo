<?php

use App\Models\Copy;
use App\Models\ExcludedMovie;
use App\Models\Movie;
use App\Models\User;
use App\Models\Watch;

test('guests cannot remove movies', function () {
    $movie = Movie::factory()->create();

    $this->delete(route('movies.destroy-many'), ['ids' => [$movie->id]])
        ->assertRedirect(route('login'));

    expect(Movie::count())->toBe(1);
});

test('removing movies deletes them and remembers them for future imports', function () {
    $doomed = Movie::factory()->count(2)->create();
    $survivor = Movie::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('movies.index'))
        ->delete(route('movies.destroy-many'), ['ids' => $doomed->modelKeys()])
        ->assertRedirect(route('movies.index'))
        ->assertInertiaFlash('toast.message', 'Removed 2 movies.');

    expect(Movie::pluck('id')->all())->toBe([$survivor->id])
        ->and(ExcludedMovie::pluck('tmdb_id')->sort()->values()->all())
        ->toBe($doomed->pluck('tmdb_id')->sort()->values()->all());
});

test('movies on a shelf or want list are kept', function () {
    $user = User::factory()->create();
    $owned = Movie::factory()->create();
    $wanted = Movie::factory()->create();
    $unwanted = Movie::factory()->create();
    Copy::factory()->for($user)->for($owned)->create();
    Watch::factory()->for($user)->for($wanted)->create();

    $this->actingAs($user)
        ->delete(route('movies.destroy-many'), ['ids' => [$owned->id, $wanted->id, $unwanted->id]])
        ->assertInertiaFlash('toast.message', 'Removed 1 movie. Kept 2 that are on your shelf or want list.');

    expect(Movie::pluck('id')->sort()->values()->all())->toBe([$owned->id, $wanted->id])
        ->and(ExcludedMovie::pluck('tmdb_id')->all())->toBe([$unwanted->tmdb_id]);
});

test('removing needs at least one movie id', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('movies.destroy-many'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
