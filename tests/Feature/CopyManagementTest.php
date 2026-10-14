<?php

use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Movie;
use App\Models\User;

test('I can add a copy of a movie to my shelf', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)
        ->from(route('movies.show', $movie))
        ->post(route('copies.store', $movie), [
            'format' => MediaFormat::Vhs->value,
            'edition' => 'Media Home Entertainment clamshell',
            'condition' => 'Good',
            'acquired_on' => '2024-10-31',
        ])
        ->assertRedirect(route('movies.show', $movie));

    $copy = Copy::sole();
    expect($copy->user_id)->toBe($user->id)
        ->and($copy->movie_id)->toBe($movie->id)
        ->and($copy->format)->toBe(MediaFormat::Vhs)
        ->and($copy->edition)->toBe('Media Home Entertainment clamshell')
        ->and($copy->acquired_on->toDateString())->toBe('2024-10-31');
});

test('a copy needs a known format and cannot be acquired in the future', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('copies.store', Movie::factory()->create()), [
            'format' => 'wax-cylinder',
            'acquired_on' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors(['format', 'acquired_on']);

    expect(Copy::count())->toBe(0);
});

test('I can update and remove my own copy', function () {
    $copy = Copy::factory()->create(['format' => MediaFormat::Dvd]);

    $this->actingAs($copy->user)
        ->patch(route('copies.update', $copy), ['format' => MediaFormat::BluRay->value, 'notes' => 'Scream Factory'])
        ->assertRedirect();
    expect($copy->fresh())->format->toBe(MediaFormat::BluRay)->notes->toBe('Scream Factory');

    $this->actingAs($copy->user)->delete(route('copies.destroy', $copy))->assertRedirect();
    expect(Copy::count())->toBe(0);
});

test('I cannot touch someone else\'s copy', function () {
    $copy = Copy::factory()->create(['format' => MediaFormat::Dvd]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('copies.update', $copy), ['format' => MediaFormat::Vhs->value])
        ->assertForbidden();
    $this->actingAs($intruder)->delete(route('copies.destroy', $copy))->assertForbidden();

    expect($copy->fresh()->format)->toBe(MediaFormat::Dvd);
});
