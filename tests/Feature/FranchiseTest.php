<?php

use App\Jobs\SyncFranchiseParts;
use App\Jobs\SyncMovieDetails;
use App\Models\Copy;
use App\Models\ExcludedMovie;
use App\Models\Franchise;
use App\Models\Movie;
use App\Models\User;
use App\Models\Watch;
use App\Services\Tmdb\CatalogWriter;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('syncing a movie links it to its series and fetches the series parts once', function () {
    Http::preventStrayRequests();
    Queue::fake([SyncFranchiseParts::class]);
    Http::fake([
        'api.themoviedb.org/3/movie/4488*' => Http::response([
            'id' => 4488,
            'title' => 'Friday the 13th',
            'release_date' => '1980-05-09',
            'belongs_to_collection' => ['id' => 9735, 'name' => 'Friday the 13th Collection', 'poster_path' => '/f13.jpg'],
        ]),
    ]);
    $movie = Movie::factory()->create(['tmdb_id' => 4488]);

    (new SyncMovieDetails($movie))->handle(app(TmdbClient::class));
    (new SyncMovieDetails($movie->fresh()))->handle(app(TmdbClient::class));

    $franchise = Franchise::sole();
    expect($franchise->tmdb_id)->toBe(9735)
        ->and($franchise->displayName())->toBe('Friday the 13th')
        ->and($movie->fresh()->franchise_id)->toBe($franchise->id);
    Queue::assertPushed(SyncFranchiseParts::class, fn (SyncFranchiseParts $job) => $job->franchise->is($franchise));
});

test('a series parts sync stocks every part except removed ones', function () {
    Http::preventStrayRequests();
    Queue::fake([SyncMovieDetails::class]);
    Http::fake([
        'api.themoviedb.org/3/collection/9735*' => Http::response([
            'id' => 9735,
            'name' => 'Friday the 13th Collection',
            'overview' => 'Camp Crystal Lake, again.',
            'parts' => [
                ['id' => 4488, 'title' => 'Friday the 13th', 'release_date' => '1980-05-09'],
                ['id' => 9725, 'title' => 'Friday the 13th Part 2', 'release_date' => '1981-04-30'],
                ['id' => 9728, 'title' => 'Jason X', 'release_date' => '2001-11-09'],
            ],
        ]),
    ]);
    $franchise = Franchise::factory()->create(['tmdb_id' => 9735, 'parts_synced_at' => null]);
    Movie::factory()->syncedAt(now())->create(['tmdb_id' => 4488]);
    ExcludedMovie::factory()->create(['tmdb_id' => 9728]);

    (new SyncFranchiseParts($franchise))->handle(app(TmdbClient::class), app(CatalogWriter::class));

    expect($franchise->movies()->orderBy('release_date')->pluck('tmdb_id')->all())->toBe([4488, 9725])
        ->and($franchise->fresh()->parts_synced_at)->not->toBeNull()
        ->and($franchise->fresh()->overview)->toBe('Camp Crystal Lake, again.');
    Queue::assertPushed(SyncMovieDetails::class, 1);
    Queue::assertPushed(SyncMovieDetails::class, fn (SyncMovieDetails $job) => $job->movie->tmdb_id === 9725);
});

test('the franchises page lists series I have started with owned counts', function () {
    $user = User::factory()->create();
    $started = Franchise::factory()->create(['name' => 'Evil Dead Collection']);
    $untouched = Franchise::factory()->create(['name' => 'Saw Collection']);
    $owned = Movie::factory()->for($started)->create();
    Movie::factory()->for($started)->count(2)->create();
    Movie::factory()->for($untouched)->create();
    Copy::factory()->for($user)->for($owned)->create();

    $this->actingAs($user)
        ->get(route('franchises.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('franchises/index')
            ->has('franchises.data', 1)
            ->where('franchises.data.0.name', 'Evil Dead')
            ->where('franchises.data.0.owned_count', 1)
            ->where('franchises.data.0.movies_count', 3));

    $this->actingAs($user)
        ->get(route('franchises.index', ['all' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('franchises.data', 2));
});

test('a series checklist shows parts in order with owned and wanted ones marked', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create();
    $second = Movie::factory()->for($franchise)->releasedIn(1981)->create();
    $first = Movie::factory()->for($franchise)->releasedIn(1980)->create();
    $third = Movie::factory()->for($franchise)->releasedIn(1982)->create();
    Copy::factory()->for($user)->for($first)->create(['format' => 'vhs']);
    Watch::factory()->for($user)->for($third)->create();

    $this->actingAs($user)
        ->get(route('franchises.show', $franchise))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('franchises/show')
            ->where('movies', fn ($movies) => collect($movies)->pluck('id')->all() === [$first->id, $second->id, $third->id])
            ->where('movies.0.owned_formats', ['vhs'])
            ->where('ownedCount', 1)
            ->where('wantedIds', [$third->id]));
});

test('a movie page shows which series it belongs to', function () {
    $user = User::factory()->create();
    $franchise = Franchise::factory()->create(['name' => 'Halloween Collection']);
    $movie = Movie::factory()->for($franchise)->create();
    Movie::factory()->for($franchise)->create();
    Copy::factory()->for($user)->for($movie)->create();

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertInertia(fn (Assert $page) => $page
            ->where('franchise.name', 'Halloween')
            ->where('franchise.owned_count', 1)
            ->where('franchise.movies_count', 2));
});
