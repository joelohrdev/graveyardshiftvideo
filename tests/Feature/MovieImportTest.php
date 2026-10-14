<?php

use App\Enums\MediaFormat;
use App\Models\Copy;
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
    $user = User::factory()->create();
    $inStore = Movie::factory()->create(['tmdb_id' => 10925]);
    Copy::factory()->for($user)->for($inStore)->create(['format' => MediaFormat::Vhs]);

    $this->actingAs($user)
        ->getJson(route('movies.tmdb-search', ['q' => 'return of the living dead']))
        ->assertOk()
        ->assertJsonPath('results.0.movie_id', $inStore->id)
        ->assertJsonPath('results.0.year', 1985)
        ->assertJsonPath('results.0.owned_formats', ['vhs'])
        ->assertJsonPath('results.1.movie_id', null)
        ->assertJsonPath('results.1.year', null);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'search/movie')
        && $request['query'] === 'return of the living dead');
});

test('a director credit in the search is dropped when the full phrase finds nothing', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/search/movie?query=Lucio%20Fulci%27s%20The%20Beyond*' => Http::response(['results' => []]),
        'api.themoviedb.org/3/search/movie?query=The%20Beyond*' => Http::response(['results' => [
            ['id' => 19204, 'title' => 'The Beyond', 'release_date' => '1981-04-29'],
        ]]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.tmdb-search', ['q' => "Lucio Fulci's The Beyond"]))
        ->assertOk()
        ->assertJsonPath('results.0.title', 'The Beyond');

    Http::assertSentCount(2);
});

function fakeFulciCredits(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(['results' => []]),
        'api.themoviedb.org/3/search/person*' => Http::response(['results' => [
            ['id' => 19429, 'name' => 'Lucio Fulci', 'known_for_department' => 'Directing'],
        ]]),
        'api.themoviedb.org/3/person/19429/movie_credits*' => Http::response([
            'crew' => [
                ['id' => 1, 'title' => 'Zombie Flesh Eaters', 'original_title' => 'Zombi 2', 'release_date' => '1979-08-25', 'job' => 'Director'],
                ['id' => 2, 'title' => 'City of the Living Dead', 'original_title' => 'Paura nella città dei morti viventi', 'release_date' => '1980-08-11', 'job' => 'Director'],
                ['id' => 3, 'title' => 'Zombi 3', 'original_title' => 'Zombi 3', 'release_date' => '1988-01-01', 'job' => 'Director'],
                ['id' => 1, 'title' => 'Zombie Flesh Eaters', 'original_title' => 'Zombi 2', 'release_date' => '1979-08-25', 'job' => 'Writer'],
            ],
            'cast' => [
                ['id' => 4, 'title' => 'Cat in the Brain', 'original_title' => 'Un gatto nel cervello', 'release_date' => '1990-01-01'],
            ],
        ]),
    ]);
}

test('a director name plus title words finds that director\'s matching movies', function () {
    fakeFulciCredits();

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.tmdb-search', ['q' => 'Lucio Fulci zombi']))
        ->assertOk()
        ->assertJsonPath('results.*.title', ['Zombie Flesh Eaters', 'Zombi 3']);
});

test('a director name alone lists their movies in release order', function () {
    fakeFulciCredits();

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.tmdb-search', ['q' => 'fulci']))
        ->assertOk()
        ->assertJsonPath('results.*.title', ['Zombie Flesh Eaters', 'City of the Living Dead', 'Zombi 3', 'Cat in the Brain']);
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

test('adding a movie with a format puts a copy on my shelf and stays in the search', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/10925*' => Http::response(tmdbDetails(10925, 'The Return of the Living Dead')),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('movies.index'))
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'The Return of the Living Dead', 'format' => 'vhs'])
        ->assertRedirect(route('movies.index'))
        ->assertInertiaFlash('toast.message', 'VHS copy of The Return of the Living Dead added to your shelf.');

    $copy = Copy::sole();
    expect($copy->user_id)->toBe($user->id)
        ->and($copy->format)->toBe(MediaFormat::Vhs)
        ->and($copy->movie->details_synced_at)->not->toBeNull();
});

test('adding a copy of a movie already in the store skips TMDB', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 10925]);
    Copy::factory()->for($user)->for($movie)->create(['format' => MediaFormat::Vhs]);

    $this->actingAs($user)
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'Anything', 'format' => 'bluray'])
        ->assertRedirect();

    expect($user->copies()->pluck('format')->map->value->sort()->values()->all())->toBe(['bluray', 'vhs'])
        ->and(Movie::count())->toBe(1);
});

test('adding to my shelf needs a known format', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('movies.store'), ['tmdb_id' => 10925, 'title' => 'Anything', 'format' => 'wax-cylinder'])
        ->assertSessionHasErrors('format');

    expect(Movie::count())->toBe(0);
});
