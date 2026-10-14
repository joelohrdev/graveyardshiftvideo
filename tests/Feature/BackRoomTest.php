<?php

use App\Jobs\ImportDiscoverPage;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('the back room shows the catalog status', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('back-room/index')
            ->where('catalog.movies', 0)
            ->where('import', null));
});

test('restocking queues a horror import for the chosen years', function () {
    config(['services.tmdb.token' => 'token']);
    Bus::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('imports.store'), ['from_year' => 1980, 'to_year' => 1981])
        ->assertRedirect(route('imports.index'));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
        && $batch->jobs->every(fn (ImportDiscoverPage $job) => $job->genreId === 27));
});

test('restocking rejects a backwards range', function () {
    config(['services.tmdb.token' => 'token']);
    Bus::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('imports.store'), ['from_year' => 1990, 'to_year' => 1980])
        ->assertSessionHasErrors('to_year');

    Bus::assertNothingBatched();
});

test('restocking without a TMDB token queues nothing', function () {
    config(['services.tmdb.token' => null]);
    Bus::fake();

    $this->actingAs(User::factory()->create())->post(route('imports.store'), []);

    Bus::assertNothingBatched();
});
