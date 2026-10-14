<?php

namespace App\Services\Ebay;

use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Searches active eBay listings through the free Browse API using an application (client credentials) token.
 */
class EbayClient
{
    public const string TokenCacheKey = 'ebay:application-token';

    /**
     * eBay US leaf categories that hold each physical format; formats without a dedicated
     * category fall back to the whole Movies & TV tree and rely on the search keyword.
     */
    public const int MoviesAndTvCategory = 11232;

    public const int DvdAndBluRayCategory = 617;

    public const int VhsCategory = 309;

    public const int LaserDiscCategory = 381;

    /**
     * Active fixed-price listings for a movie, with shipping folded into the price.
     *
     * @return list<array{title: string, price: float, url: string}>
     */
    public function searchListings(string $movieTitle, ?MediaFormat $format, ListingCondition $condition): array
    {
        $response = $this->request()
            ->get('buy/browse/v1/item_summary/search', [
                'q' => trim($movieTitle.' '.$this->formatKeyword($format)),
                'category_ids' => $this->categoryFor($format),
                'filter' => $this->filterFor($condition),
                'limit' => 100,
            ])
            ->throw();

        $listings = [];

        foreach ($response->json('itemSummaries') ?? [] as $item) {
            if (! is_array($item) || ! isset($item['title'], $item['price']['value'], $item['itemWebUrl'])) {
                continue;
            }

            $listings[] = [
                'title' => (string) $item['title'],
                'price' => round((float) $item['price']['value'] + $this->shippingCost($item), 2),
                'url' => (string) $item['itemWebUrl'],
            ];
        }

        return $listings;
    }

    public function categoryFor(?MediaFormat $format): int
    {
        return match ($format) {
            MediaFormat::Dvd, MediaFormat::BluRay, MediaFormat::UltraHd => self::DvdAndBluRayCategory,
            MediaFormat::Vhs => self::VhsCategory,
            MediaFormat::LaserDisc => self::LaserDiscCategory,
            default => self::MoviesAndTvCategory,
        };
    }

    public function formatKeyword(?MediaFormat $format): string
    {
        return match ($format) {
            MediaFormat::Vhs => 'vhs',
            MediaFormat::Betamax => 'betamax',
            MediaFormat::LaserDisc => 'laserdisc',
            MediaFormat::Dvd => 'dvd',
            MediaFormat::BluRay => 'blu-ray',
            MediaFormat::UltraHd => '4k',
            default => '',
        };
    }

    /**
     * Auctions show a running bid rather than a buy price, so only fixed-price listings are compared.
     */
    public function filterFor(ListingCondition $condition): string
    {
        $filters = ['buyingOptions:{FIXED_PRICE}', 'priceCurrency:USD'];

        if ($condition !== ListingCondition::Any) {
            $filters[] = 'conditions:{'.strtoupper($condition->value).'}';
        }

        return implode(',', $filters);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function shippingCost(array $item): float
    {
        return (float) ($item['shippingOptions'][0]['shippingCost']['value'] ?? 0);
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('services.ebay.base_url'))
            ->withToken($this->applicationToken())
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => config('services.ebay.marketplace')])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(
                3,
                fn (int $attempt): int => $attempt * 1000,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests())),
                throw: false,
            );
    }

    /**
     * Client-credentials tokens last two hours; cache them until shortly before they expire.
     */
    public function applicationToken(): string
    {
        $cached = Cache::get(self::TokenCacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        $token = Http::baseUrl(config('services.ebay.base_url'))
            ->withBasicAuth((string) config('services.ebay.client_id'), (string) config('services.ebay.client_secret'))
            ->asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post('identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ])
            ->throw()
            ->json();

        Cache::put(self::TokenCacheKey, $token['access_token'], max(60, (int) $token['expires_in'] - 120));

        return $token['access_token'];
    }
}
