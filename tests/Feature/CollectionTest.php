<?php

use App\Enums\CreditRole;
use App\Enums\DealStatus;
use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Keyword;
use App\Models\Movie;
use App\Models\Person;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\Watch;
use Inertia\Testing\AssertableInertia as Assert;

function directedBy(Person $director, Movie $movie): Movie
{
    $movie->people()->attach($director, ['role' => CreditRole::Director->value, 'order' => 0]);

    return $movie;
}

test('shelves group my movies by director, biggest shelf first', function () {
    $user = User::factory()->create();
    $carpenter = Person::factory()->create(['name' => 'John Carpenter']);
    $raimi = Person::factory()->create(['name' => 'Sam Raimi']);
    $halloween = directedBy($carpenter, Movie::factory()->releasedIn(1978)->create(['title' => 'Halloween']));
    $theThing = directedBy($carpenter, Movie::factory()->releasedIn(1982)->create(['title' => 'The Thing']));
    $evilDead = directedBy($raimi, Movie::factory()->releasedIn(1981)->create(['title' => 'The Evil Dead']));
    $notMine = directedBy($raimi, Movie::factory()->create(['title' => 'Darkman']));
    foreach ([$halloween, $theThing, $evilDead] as $movie) {
        Copy::factory()->for($user)->for($movie)->create(['format' => MediaFormat::Vhs]);
    }
    Copy::factory()->for($notMine)->create();

    $this->actingAs($user)
        ->get(route('collection.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('collection/index')
            ->where('group', 'director')
            ->where('shelves.0.label', 'John Carpenter')
            ->where('shelves.0.person_id', $carpenter->id)
            ->where('shelves.0.movies.0.title', 'Halloween')
            ->where('shelves.0.movies.1.title', 'The Thing')
            ->where('shelves.1.label', 'Sam Raimi')
            ->has('shelves.1.movies', 1)
            ->where('stats.movies', 3)
            ->where('stats.copies', 3)
            ->where('stats.directors', 2));
});

test('shelves group by decade and by format', function () {
    $user = User::factory()->create();
    $eighties = Movie::factory()->releasedIn(1984)->create();
    $seventies = Movie::factory()->releasedIn(1978)->create();
    Copy::factory()->for($user)->for($eighties)->create(['format' => MediaFormat::Vhs]);
    Copy::factory()->for($user)->for($eighties)->create(['format' => MediaFormat::UltraHd]);
    Copy::factory()->for($user)->for($seventies)->create(['format' => MediaFormat::Vhs]);

    $this->actingAs($user)
        ->get(route('collection.index', ['group' => 'decade']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shelves', fn ($shelves) => collect($shelves)->pluck('label')->all() === ['1970s', '1980s']));

    $this->actingAs($user)
        ->get(route('collection.index', ['group' => 'format']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shelves.0.label', 'VHS')
            ->has('shelves.0.movies', 2)
            ->where('shelves.1.label', '4K UHD')
            ->has('shelves.1.movies', 1));
});

test('subgenres with a single movie fall into everything else', function () {
    $user = User::factory()->create();
    $slasher = Keyword::factory()->create(['name' => 'slasher']);
    $zombie = Keyword::factory()->create(['name' => 'zombie']);
    $movies = Movie::factory()->count(3)->create();
    $movies[0]->keywords()->attach($slasher);
    $movies[1]->keywords()->attach($slasher);
    $movies[2]->keywords()->attach($zombie);
    $movies->each(fn (Movie $movie) => Copy::factory()->for($user)->for($movie)->create());

    $this->actingAs($user)
        ->get(route('collection.index', ['group' => 'subgenre']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shelves.0.label', 'slasher')
            ->has('shelves.0.movies', 2)
            ->where('shelves.1.label', 'Everything else')
            ->has('shelves', 2));
});

test('hot deals only include good deals and steals', function () {
    $user = User::factory()->create();
    $steal = Watch::factory()->for($user)->create(['target_price' => 20]);
    PriceSnapshot::factory()->for($steal)->create(['lowest_price' => 15]);
    $waiting = Watch::factory()->for($user)->create();
    PriceSnapshot::factory()->for($waiting)->create(['lowest_price' => 15]);

    $this->actingAs($user)
        ->get(route('collection.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('hotDeals', 1)
            ->where('hotDeals.0.id', $steal->id)
            ->where('hotDeals.0.deal_status', DealStatus::Steal->value)
            ->where('hotDeals.0.movie.title', $steal->movie->title));
});

test('an unknown grouping is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('collection.index', ['group' => 'color']))
        ->assertSessionHasErrors('group');
});
