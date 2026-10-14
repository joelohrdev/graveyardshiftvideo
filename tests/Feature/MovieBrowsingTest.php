<?php

use App\Enums\CreditRole;
use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Person;
use App\Models\User;
use App\Models\Watch;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot browse the aisles', function () {
    $this->get(route('movies.index'))->assertRedirect(route('login'));
});

test('the aisles filter by decade, genre and search', function () {
    $user = User::factory()->create();
    $horror = Genre::factory()->create(['name' => 'Horror']);
    $match = Movie::factory()->releasedIn(1983)->create(['title' => 'Christine']);
    $match->genres()->attach($horror);
    Movie::factory()->releasedIn(1983)->create(['title' => 'Cujo']);
    Movie::factory()->releasedIn(1993)->create(['title' => 'Christine 2'])->genres()->attach($horror);

    $this->actingAs($user)
        ->get(route('movies.index', ['q' => 'Christ', 'decade' => 1980, 'genre' => $horror->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('aisles/index')
            ->has('movies.data', 1)
            ->where('movies.data.0.title', 'Christine'));
});

test('the aisles search matches director names', function () {
    $fulci = Person::factory()->create(['name' => 'Lucio Fulci']);
    $beyond = Movie::factory()->create(['title' => 'The Beyond']);
    $beyond->people()->attach($fulci, ['role' => CreditRole::Director->value, 'order' => 0]);
    Movie::factory()->create(['title' => 'Inferno']);

    $this->actingAs(User::factory()->create())
        ->get(route('movies.index', ['q' => 'fulci']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('movies.data', 1)
            ->where('movies.data.0.title', 'The Beyond'));
});

test('sorting by title ignores a leading article', function () {
    foreach (['The Thing', 'Alien', 'A Bay of Blood', 'Zombi 2'] as $title) {
        Movie::factory()->create(['title' => $title]);
    }

    $this->actingAs(User::factory()->create())
        ->get(route('movies.index', ['sort' => 'title']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('movies.data', fn ($movies) => collect($movies)->pluck('title')->all() === ['Alien', 'A Bay of Blood', 'The Thing', 'Zombi 2']));
});

test('the aisles can show only what I own in a format, with owned formats on each card', function () {
    $user = User::factory()->create();
    $vhs = Movie::factory()->create();
    $dvd = Movie::factory()->create();
    Movie::factory()->create();
    Copy::factory()->for($user)->for($vhs)->create(['format' => MediaFormat::Vhs]);
    Copy::factory()->for($user)->for($dvd)->create(['format' => MediaFormat::Dvd]);
    Copy::factory()->for($dvd)->create(['format' => MediaFormat::Vhs]);

    $this->actingAs($user)
        ->get(route('movies.index', ['format' => MediaFormat::Vhs->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('movies.data', 1)
            ->where('movies.data.0.id', $vhs->id)
            ->where('movies.data.0.owned_formats', ['vhs']));

    $this->actingAs($user)
        ->get(route('movies.index', ['owned' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('movies.data', 2));
});

test('the tape box shows only my copies and watches', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $mine = Copy::factory()->for($user)->for($movie)->create();
    Copy::factory()->for($movie)->create();
    Watch::factory()->for($movie)->create();

    $this->actingAs($user)
        ->get(route('movies.show', $movie))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('movies/show')
            ->where('movie.id', $movie->id)
            ->has('copies', 1)
            ->where('copies.0.id', $mine->id)
            ->has('watches', 0));
});

test('a person page splits directing and acting and marks what I own', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create();
    $directed = Movie::factory()->create();
    $starred = Movie::factory()->create();
    $directed->people()->attach($person, ['role' => CreditRole::Director->value, 'order' => 0]);
    $starred->people()->attach($person, ['role' => CreditRole::Cast->value, 'character' => 'Ash', 'order' => 0]);
    Copy::factory()->for($user)->for($directed)->create(['format' => MediaFormat::BluRay]);

    $this->actingAs($user)
        ->get(route('people.show', $person))
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('directed.0.id', $directed->id)
            ->where('directed.0.owned_formats', ['bluray'])
            ->where('starred.0.id', $starred->id)
            ->where('starred.0.owned_formats', [])
            ->where('ownedCount', 1));
});

test('directors row defaults to directors on my shelf, ranked by how many I own', function () {
    $user = User::factory()->create();
    $carpenter = Person::factory()->create(['name' => 'John Carpenter']);
    $craven = Person::factory()->create(['name' => 'Wes Craven']);
    $stranger = Person::factory()->create(['name' => 'Not Owned']);
    foreach ([[$carpenter, 2], [$craven, 1], [$stranger, 0]] as [$director, $owned]) {
        Movie::factory()->count(2)->create()->each(function (Movie $movie, int $index) use ($director, $owned, $user) {
            $movie->people()->attach($director, ['role' => CreditRole::Director->value, 'order' => 0]);
            if ($index < $owned) {
                Copy::factory()->for($user)->for($movie)->create();
            }
        });
    }

    $this->actingAs($user)
        ->get(route('directors.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('directors/index')
            ->has('directors.data', 2)
            ->where('directors.data.0.name', 'John Carpenter')
            ->where('directors.data.0.owned_count', 2)
            ->where('directors.data.0.directed_count', 2)
            ->where('directors.data.1.name', 'Wes Craven'));

    $this->actingAs($user)
        ->get(route('directors.index', ['all' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('directors.data', 3));
});
