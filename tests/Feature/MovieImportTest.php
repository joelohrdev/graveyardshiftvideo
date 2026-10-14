<?php

use App\Models\ExcludedMovie;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function tmdbDetails(int $id, string $title): array
{
    return [
        'id' => $id,
        'title' => $title,
        'release_date' => '1985-08-16',
        'runtime' => 91,
        'genres' => [['id' => 35, 'name' => 'Comedy']],
        'keywords' => ['keywords' => [['id' => 1, 'name' => 'zombie']]],
        'credits' => ['crew' => [['id' => 7, 'name' => 'Dan O\'Bannon', 'job' => 'Director']], 'cast' => []],
    ];
}

test('guests cannot search TMDB', function () {
    $this->getJson(route('movies.tmdb-search', ['q' => 'Return']))->assertUnauthorized();
});

test('searching TMDB flags titles already in the store', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
            ['id' => 10925, 'title' => 'The Return of the Living Dead', 'release_date' => '1985-08-16', 'poster_path' => '/rotld.jpg'],
            ['id' => 999, 'title' => 'Return of the Living Dead Part II', 'release_date' => ''],
        ]]),
    ]);
    $inStore = Movie::factory()->create(['tmdb_id' => 10925]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.tmdb-search', ['q' => 'return of the living dead']))
        ->assertOk()
        ->assertJsonPath('results.0.movie_id', $inStore->id)
        ->assertJsonPath('results.0.year', 1985)
        ->assertJsonPath('results.1.movie_id', null)
        ->assertJsonPath('results.1.year', null);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'search/movie')
        && $request['query'] === 'return of the living dead');
});

test('adding a movie fetches its full TMDB record and opens it', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/10925*' => Http::response(tmdbDetails(10925, 'The Return of the Living Dead')),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'The Return of the Living Dead']);

    $movie = Movie::sole();
    $response->assertRedirect(route('movies.show', $movie));
    expect($movie->runtime)->toBe(91)
        ->and($movie->details_synced_at)->not->toBeNull()
        ->and($movie->directors->pluck('name')->all())->toBe(['Dan O\'Bannon']);
});

test('adding a movie that is already in the store just opens it', function () {
    Http::preventStrayRequests();
    $movie = Movie::factory()->create(['tmdb_id' => 10925]);

    $this->actingAs(User::factory()->create())
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'Anything'])
        ->assertRedirect(route('movies.show', $movie));

    expect(Movie::count())->toBe(1);
});

test('adding a movie by hand undoes an earlier removal', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/10925*' => Http::response(tmdbDetails(10925, 'The Return of the Living Dead')),
    ]);
    ExcludedMovie::factory()->create(['tmdb_id' => 10925]);

    $this->actingAs(User::factory()->create())
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'The Return of the Living Dead']);

    expect(ExcludedMovie::count())->toBe(0)
        ->and(Movie::count())->toBe(1);
});
