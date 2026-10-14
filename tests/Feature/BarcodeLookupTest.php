<?php

use App\Enums\MediaFormat;
use App\Models\Copy;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.ebay.client_id' => 'id', 'services.ebay.client_secret' => 'secret']);
});

/**
 * @param  list<string>  $titles
 */
function fakeBarcodeListings(array $titles): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 7200]),
        'api.ebay.com/buy/browse/v1/item_summary/search*' => Http::response([
            'itemSummaries' => array_map(fn (string $title) => [
                'title' => $title,
                'categories' => [['categoryId' => '309', 'categoryName' => 'VHS Tapes']],
            ], $titles),
        ]),
    ]);
}

test('a barcode is identified from eBay listings for that product', function () {
    fakeBarcodeListings([
        'The Beyond (VHS, 1981) Lucio Fulci Horror RARE',
        'The Beyond VHS 1981 Fulci cult classic big box',
        'THE BEYOND - VHS - Ex-Rental',
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.barcode', ['code' => '012236103534']))
        ->assertOk()
        ->assertExactJson([
            'barcode' => '012236103534',
            'title' => 'The Beyond',
            'year' => 1981,
            'format' => 'vhs',
            'listings' => [
                'The Beyond (VHS, 1981) Lucio Fulci Horror RARE',
                'The Beyond VHS 1981 Fulci cult classic big box',
                'THE BEYOND - VHS - Ex-Rental',
            ],
        ]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'item_summary/search')
        && $request['gtin'] === '012236103534');
});

test('scanning a case already on my shelf says so without asking eBay', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['title' => 'The Beyond']);
    Copy::factory()->for($user)->for($movie)->create(['format' => MediaFormat::Vhs, 'barcode' => '012236103534']);

    $this->actingAs($user)
        ->getJson(route('movies.barcode', ['code' => '012236103534']))
        ->assertOk()
        ->assertJsonPath('owned.movie_id', $movie->id)
        ->assertJsonPath('owned.format_label', 'VHS');
});

test('barcode lookup explains when eBay is not set up', function () {
    config(['services.ebay.client_id' => null]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.barcode', ['code' => '012236103534']))
        ->assertStatus(503);
});

test('a barcode must be 8 to 14 digits', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('movies.barcode', ['code' => 'abc123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('adding a scanned copy keeps its barcode', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    Movie::factory()->create(['tmdb_id' => 19204]);

    $this->actingAs($user)
        ->post(route('movies.store'), ['tmdb_id' => 19204, 'title' => 'The Beyond', 'format' => 'vhs', 'barcode' => '012236103534'])
        ->assertRedirect();

    expect(Copy::sole()->barcode)->toBe('012236103534');
});
