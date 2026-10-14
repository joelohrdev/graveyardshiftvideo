<?php

use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use App\Jobs\SnapshotWatchPrices;
use App\Models\Movie;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('I can add a movie to my want list', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)
        ->post(route('watches.store', $movie), [
            'format' => MediaFormat::Vhs->value,
            'condition' => ListingCondition::Used->value,
            'target_price' => '14.99',
        ])
        ->assertRedirect();

    expect(Watch::sole())
        ->user_id->toBe($user->id)
        ->format->toBe(MediaFormat::Vhs)
        ->condition->toBe(ListingCondition::Used)
        ->target_price->toBe(14.99)
        ->active->toBeTrue();
});

test('digital copies cannot be priced on eBay', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('watches.store', Movie::factory()->create()), [
            'format' => MediaFormat::Digital->value,
            'condition' => ListingCondition::Any->value,
        ])
        ->assertSessionHasErrors('format');
});

test('the want list lists only my watches, best deals first', function () {
    $user = User::factory()->create();
    $hold = Watch::factory()->for($user)->create();
    $steal = Watch::factory()->for($user)->create(['target_price' => 20]);
    PriceSnapshot::factory()->for($steal)->create(['lowest_price' => 10]);
    Watch::factory()->create();

    $this->actingAs($user)
        ->get(route('watches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('want-list/index')
            ->has('watches', 2)
            ->where('watches.0.id', $steal->id)
            ->where('watches.0.latest.lowest_price', 10)
            ->where('watches.1.id', $hold->id));
});

test('I cannot change or check someone else\'s watch', function () {
    $watch = Watch::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('watches.update', $watch), ['condition' => ListingCondition::New->value])
        ->assertForbidden();
    $this->actingAs($intruder)->delete(route('watches.destroy', $watch))->assertForbidden();
    $this->actingAs($intruder)->post(route('watches.check', $watch))->assertForbidden();

    expect(Watch::count())->toBe(1);
});

test('checking now prices the watch immediately', function () {
    config(['services.ebay.client_id' => 'id', 'services.ebay.client_secret' => 'secret']);
    Http::preventStrayRequests();
    Http::fake([
        'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 7200]),
        'api.ebay.com/buy/browse/v1/item_summary/search*' => Http::response(['itemSummaries' => [
            ['title' => 'Phantasm VHS', 'price' => ['value' => '18.00'], 'itemWebUrl' => 'https://www.ebay.com/itm/1'],
        ]]),
    ]);
    $watch = Watch::factory()->for(Movie::factory()->state(['title' => 'Phantasm']))->create(['format' => MediaFormat::Vhs]);

    $this->actingAs($watch->user)->post(route('watches.check', $watch))->assertRedirect();

    expect($watch->snapshots()->sole()->lowest_price)->toBe(18.0);
});

test('checking without eBay keys explains what is missing', function () {
    config(['services.ebay.client_id' => null, 'services.ebay.client_secret' => null]);
    Queue::fake([SnapshotWatchPrices::class]);
    $watch = Watch::factory()->create();

    $this->actingAs($watch->user)
        ->post(route('watches.check', $watch))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect($watch->snapshots()->count())->toBe(0);
});
