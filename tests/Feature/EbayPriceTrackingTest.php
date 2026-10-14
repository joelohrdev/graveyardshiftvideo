<?php

use App\Enums\DealStatus;
use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use App\Jobs\SnapshotWatchPrices;
use App\Models\Copy;
use App\Models\Movie;
use App\Models\PriceSnapshot;
use App\Models\Watch;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\ListingFilter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @param  list<array{0: string, 1: float, 2?: float}>  $listings  title, price, shipping
 */
function fakeEbay(array $listings): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 7200]),
        'api.ebay.com/buy/browse/v1/item_summary/search*' => Http::response([
            'itemSummaries' => array_map(fn (array $listing, int $index) => [
                'title' => $listing[0],
                'price' => ['value' => (string) $listing[1], 'currency' => 'USD'],
                'shippingOptions' => [['shippingCost' => ['value' => (string) ($listing[2] ?? 0), 'currency' => 'USD']]],
                'itemWebUrl' => "https://www.ebay.com/itm/{$index}",
            ], $listings, array_keys($listings)),
        ]),
    ]);
}

test('the application token is fetched once and reused from cache', function () {
    fakeEbay([]);
    $client = app(EbayClient::class);

    $client->searchListings('Halloween', MediaFormat::Vhs, ListingCondition::Any);
    $client->searchListings('Halloween', MediaFormat::Vhs, ListingCondition::Any);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'item_summary/search')
        && $request->hasHeader('Authorization', 'Bearer app-token'));
});

test('searches target the format category, keyword and condition', function (MediaFormat $format, int $category, string $keyword) {
    fakeEbay([]);

    app(EbayClient::class)->searchListings('The Thing', $format, ListingCondition::Used);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'item_summary/search')
        && $request['q'] === "The Thing {$keyword}"
        && (int) $request['category_ids'] === $category
        && $request['filter'] === 'buyingOptions:{FIXED_PRICE},priceCurrency:USD,conditions:{USED}');
})->with([
    'vhs' => [MediaFormat::Vhs, EbayClient::VhsCategory, 'vhs'],
    'blu-ray' => [MediaFormat::BluRay, EbayClient::DvdAndBluRayCategory, 'blu-ray'],
    '4k' => [MediaFormat::UltraHd, EbayClient::DvdAndBluRayCategory, '4k'],
]);

test('the listing filter keeps real copies and drops noise', function (string $title, ?MediaFormat $format, bool $accepted) {
    expect(app(ListingFilter::class)->accepts($title, 'The Thing', $format))->toBe($accepted);
})->with([
    'vhs copy' => ['The Thing (1982) VHS John Carpenter Universal', MediaFormat::Vhs, true],
    'wrong format' => ['The Thing DVD widescreen', MediaFormat::Vhs, false],
    'lot' => ['Horror VHS lot The Thing Halloween Fog', MediaFormat::Vhs, false],
    'poster' => ['The Thing 1982 movie poster 27x40', null, false],
    'empty case' => ['The Thing Blu-ray case only no disc', MediaFormat::BluRay, false],
    'other movie' => ['Something Wild VHS', MediaFormat::Vhs, false],
    'blu-ray, not 4k' => ['The Thing 4K UHD + Blu-ray Collector\'s Edition', MediaFormat::BluRay, false],
    '4k' => ['The Thing 4K UHD + Blu-ray Collector\'s Edition', MediaFormat::UltraHd, true],
    'dvd' => ['The Thing - Collector\'s Edition (DVD)', MediaFormat::Dvd, true],
]);

test('a snapshot stores today\'s filtered prices with shipping included', function () {
    $this->freezeTime();
    fakeEbay([
        ['Halloween VHS Media Home Entertainment', 20.00, 5.00],
        ['Halloween VHS 1978 clamshell', 12.00, 0.00],
        ['Halloween VHS 1979 first release', 40.00, 0.00],
        ['Halloween VHS lot of 5', 1.00, 0.00],
    ]);
    $watch = Watch::factory()->for(Movie::factory()->state(['title' => 'Halloween']))->create(['format' => MediaFormat::Vhs]);

    (new SnapshotWatchPrices($watch))->handle(app(EbayClient::class), app(ListingFilter::class));

    $snapshot = $watch->snapshots()->sole();
    expect($snapshot->checked_on->isToday())->toBeTrue()
        ->and($snapshot->listing_count)->toBe(3)
        ->and($snapshot->lowest_price)->toBe(12.0)
        ->and($snapshot->median_price)->toBe(25.0)
        ->and($snapshot->highest_price)->toBe(40.0)
        ->and($snapshot->lowest_listing_url)->toBe('https://www.ebay.com/itm/1')
        ->and($watch->fresh()->last_checked_at)->not->toBeNull();
});

test('checking twice in one day updates the same snapshot', function () {
    fakeEbay([['Halloween VHS', 10.00]]);
    $watch = Watch::factory()->for(Movie::factory()->state(['title' => 'Halloween']))->create(['format' => MediaFormat::Vhs]);

    (new SnapshotWatchPrices($watch))->handle(app(EbayClient::class), app(ListingFilter::class));
    (new SnapshotWatchPrices($watch))->handle(app(EbayClient::class), app(ListingFilter::class));

    expect($watch->snapshots()->count())->toBe(1);
});

test('the snapshot command queues only active watches', function () {
    Queue::fake([SnapshotWatchPrices::class]);
    $active = Watch::factory()->create();
    Watch::factory()->create(['active' => false]);

    $this->artisan('ebay:snapshot-prices')->assertSuccessful();

    Queue::assertPushed(SnapshotWatchPrices::class, 1);
    Queue::assertPushed(SnapshotWatchPrices::class, fn (SnapshotWatchPrices $job) => $job->watch->is($active));
});

/**
 * @param  list<float>  $dailyLows  oldest first; the last one is today
 */
function watchWithHistory(array $dailyLows, ?float $targetPrice = null): Watch
{
    $watch = Watch::factory()->create(['target_price' => $targetPrice]);
    $days = count($dailyLows);

    foreach ($dailyLows as $index => $low) {
        PriceSnapshot::factory()->for($watch)->create([
            'checked_on' => today()->subDays($days - 1 - $index),
            'lowest_price' => $low,
        ]);
    }

    return $watch->load('snapshots');
}

test('deal status', function (array $dailyLows, ?float $target, DealStatus $expected) {
    expect(watchWithHistory($dailyLows, $target)->dealStatus())->toBe($expected);
})->with([
    'no snapshots yet' => [[], null, DealStatus::Wait],
    'at target even without history' => [[15.0], 15.0, DealStatus::Steal],
    'too little history' => [[20, 20, 20, 20, 20, 10], null, DealStatus::Wait],
    '20% under the trailing median' => [[20, 20, 20, 20, 20, 20, 16], null, DealStatus::GoodDeal],
    'just above the deal line' => [[20, 20, 20, 20, 20, 20, 16.01], null, DealStatus::Hold],
    'above target falls back to history' => [[20, 20, 20, 20, 20, 20, 20], 10.0, DealStatus::Hold],
]);

test('adding a copy stops pricing matching want-list entries', function () {
    $watchedVhs = Watch::factory()->create(['format' => MediaFormat::Vhs]);
    $watchedAnyFormat = Watch::factory()->for($watchedVhs->user)->for($watchedVhs->movie)->create(['format' => null]);
    $watchedBluRay = Watch::factory()->for($watchedVhs->user)->for($watchedVhs->movie)->create(['format' => MediaFormat::BluRay]);

    Copy::factory()->for($watchedVhs->user)->for($watchedVhs->movie)->create(['format' => MediaFormat::Vhs]);

    expect($watchedVhs->fresh()->active)->toBeFalse()
        ->and($watchedAnyFormat->fresh()->active)->toBeFalse()
        ->and($watchedBluRay->fresh()->active)->toBeTrue();
});
