<?php

use App\Actions\StartGenreImport;
use App\Enums\CreditRole;
use App\Jobs\ImportDiscoverPage;
use App\Jobs\SyncMovieDetails;
use App\Models\Copy;
use App\Models\Movie;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @param  list<array<string, mixed>>  $results
 * @return array<string, mixed>
 */
function discoverPage(array $results, int $page = 1, int $totalPages = 1): array
{
    return ['page' => $page, 'total_pages' => $totalPages, 'total_results' => count($results), 'results' => $results];
}

/**
 * @return array<string, mixed>
 */
function discoverResult(int $id, string $title, string $releaseDate = '1981-05-01'): array
{
    return [
        'id' => $id,
        'title' => $title,
        'original_title' => $title,
        'overview' => "{$title} overview",
        'release_date' => $releaseDate,
        'poster_path' => "/{$id}.jpg",
        'backdrop_path' => null,
        'original_language' => 'en',
        'vote_average' => 7.1,
        'vote_count' => 1200,
        'popularity' => 33.5,
        'genre_ids' => [27],
    ];
}

test('a discover page upserts movies and queues details for the unsynced ones', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/discover/movie*' => Http::response(discoverPage([
            discoverResult(10, 'The Evil Dead'),
            discoverResult(11, 'An American Werewolf in London'),
        ])),
    ]);
    Queue::fake([SyncMovieDetails::class]);
    Movie::factory()->create(['tmdb_id' => 11, 'title' => 'Old title', 'details_synced_at' => now()]);

    (new ImportDiscoverPage(27, 1981))->handle(app(TmdbClient::class));

    expect(Movie::query()->orderBy('tmdb_id')->pluck('title', 'tmdb_id')->all())
        ->toBe([10 => 'The Evil Dead', 11 => 'An American Werewolf in London']);
    expect(Movie::firstWhere('tmdb_id', 10)->release_year)->toBe(1981);
    Queue::assertPushed(SyncMovieDetails::class, 1);
    Queue::assertPushed(SyncMovieDetails::class, fn (SyncMovieDetails $job) => $job->movie->tmdb_id === 10);
});

test('the first discover page fans the remaining pages into the batch', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/discover/movie*' => Http::response(discoverPage([discoverResult(10, 'The Evil Dead')], 1, 3)),
    ]);

    [$job, $batch] = (new ImportDiscoverPage(27, 1981))->withFakeBatch();

    $job->handle(app(TmdbClient::class));

    $pageJobs = collect($batch->added)->whereInstanceOf(ImportDiscoverPage::class);
    expect($pageJobs->pluck('page')->all())->toBe([2, 3]);
    expect(collect($batch->added)->whereInstanceOf(SyncMovieDetails::class))->toHaveCount(1);
});

test('re-running a discover page does not duplicate movies', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/discover/movie*' => Http::response(discoverPage([discoverResult(10, 'The Evil Dead')])),
    ]);
    Queue::fake([SyncMovieDetails::class]);

    (new ImportDiscoverPage(27, 1981))->handle(app(TmdbClient::class));
    (new ImportDiscoverPage(27, 1981))->handle(app(TmdbClient::class));

    expect(Movie::count())->toBe(1);
});

test('syncing details stores directors, top cast, genres and keywords', function () {
    Http::preventStrayRequests();
    $movie = Movie::factory()->syncedAt(null)->create(['tmdb_id' => 948, 'runtime' => null]);
    Http::fake([
        'api.themoviedb.org/3/movie/948*' => Http::response([
            'id' => 948,
            'title' => 'Halloween',
            'original_title' => 'Halloween',
            'overview' => 'The night HE came home.',
            'release_date' => '1978-10-24',
            'runtime' => 91,
            'poster_path' => '/halloween.jpg',
            'genres' => [['id' => 27, 'name' => 'Horror'], ['id' => 53, 'name' => 'Thriller']],
            'keywords' => ['keywords' => [['id' => 12339, 'name' => 'slasher']]],
            'credits' => [
                'crew' => [
                    ['id' => 11218, 'name' => 'John Carpenter', 'job' => 'Director', 'profile_path' => null],
                    ['id' => 11218, 'name' => 'John Carpenter', 'job' => 'Original Music Composer', 'profile_path' => null],
                ],
                'cast' => collect(range(1, 12))->map(fn (int $order) => [
                    'id' => 5000 + $order, 'name' => "Actor {$order}", 'character' => "Role {$order}", 'order' => $order, 'profile_path' => null,
                ])->all(),
            ],
        ]),
    ]);

    (new SyncMovieDetails($movie))->handle(app(TmdbClient::class));

    $movie->refresh();
    expect($movie->title)->toBe('Halloween')
        ->and($movie->runtime)->toBe(91)
        ->and($movie->release_year)->toBe(1978)
        ->and($movie->details_synced_at)->not->toBeNull()
        ->and($movie->directors->pluck('name')->all())->toBe(['John Carpenter'])
        ->and($movie->cast)->toHaveCount(SyncMovieDetails::CastLimit)
        ->and($movie->cast->first()->pivot->character)->toBe('Role 1')
        ->and($movie->genres->pluck('name')->sort()->values()->all())->toBe(['Horror', 'Thriller'])
        ->and($movie->keywords->pluck('name')->all())->toBe(['slasher']);
});

test('syncing details again replaces credits instead of stacking them', function () {
    Http::preventStrayRequests();
    $movie = Movie::factory()->create(['tmdb_id' => 948]);
    Http::fake([
        'api.themoviedb.org/3/movie/948*' => Http::response([
            'id' => 948, 'title' => 'Halloween', 'release_date' => '1978-10-24',
            'credits' => ['crew' => [['id' => 11218, 'name' => 'John Carpenter', 'job' => 'Director']], 'cast' => []],
        ]),
    ]);

    (new SyncMovieDetails($movie))->handle(app(TmdbClient::class));
    (new SyncMovieDetails($movie))->handle(app(TmdbClient::class));

    expect($movie->people()->wherePivot('role', CreditRole::Director->value)->count())->toBe(1);
});

test('a movie TMDB no longer has is removed unless it is in the collection', function () {
    Http::preventStrayRequests();
    $forgotten = Movie::factory()->create(['tmdb_id' => 1]);
    $owned = Movie::factory()->create(['tmdb_id' => 2]);
    Copy::factory()->for($owned)->create();
    Http::fake([
        'api.themoviedb.org/3/movie/*' => Http::response(['status_message' => 'Not found'], 404),
    ]);

    (new SyncMovieDetails($forgotten))->handle(app(TmdbClient::class));
    (new SyncMovieDetails($owned))->handle(app(TmdbClient::class));

    expect(Movie::pluck('tmdb_id')->all())->toBe([2]);
});

test('starting an import batches one first-page job per year', function () {
    Bus::fake();

    app(StartGenreImport::class)->handle(27, 1980, 1982);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->pluck('year')->all() === [1982, 1981, 1980]
        && $batch->jobs->every(fn (ImportDiscoverPage $job) => $job->page === 1 && $job->genreId === 27));
});

test('the import command rejects a backwards year range', function () {
    Bus::fake();

    $this->artisan('tmdb:import', ['--from' => 1990, '--to' => 1980])->assertFailed();

    Bus::assertNothingBatched();
});

test('the stale refresh only queues movies older than five months, up to the daily cap', function () {
    Queue::fake([SyncMovieDetails::class]);
    $this->freezeTime();
    Movie::factory()->count(148)->syncedAt(now()->subMonth())->create();
    $neverSynced = Movie::factory()->syncedAt(null)->create();
    $oldest = Movie::factory()->syncedAt(now()->subMonths(7))->create();
    Movie::factory()->syncedAt(now()->subMonths(6))->create();

    // 151 movies / 150 days → a cap of 2 per day.
    $this->artisan('tmdb:refresh-stale')->assertSuccessful();

    Queue::assertPushed(SyncMovieDetails::class, 2);
    Queue::assertPushed(SyncMovieDetails::class, fn (SyncMovieDetails $job) => $job->movie->is($neverSynced));
    Queue::assertPushed(SyncMovieDetails::class, fn (SyncMovieDetails $job) => $job->movie->is($oldest));
});
