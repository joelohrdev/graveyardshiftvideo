<?php

namespace App\Jobs;

use App\Models\Watch;
use App\Services\Ebay\EbayClient;
use App\Services\Ebay\ListingFilter;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Records today's eBay asking prices for one want-list entry.
 *
 * eBay's free API only exposes active listings, so the app builds its own price history from these daily snapshots.
 */
class SnapshotWatchPrices implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Watch $watch) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ebay')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(EbayClient $ebay, ListingFilter $listingFilter): void
    {
        $movieTitle = $this->watch->movie->title;

        $listings = $listingFilter->filter(
            $ebay->searchListings($movieTitle, $this->watch->format, $this->watch->condition),
            $movieTitle,
            $this->watch->format,
        );

        $prices = collect($listings)->pluck('price')->sort()->values();
        $cheapest = collect($listings)->sortBy('price')->first();

        $this->watch->snapshots()->updateOrCreate(
            ['checked_on' => today()],
            [
                'listing_count' => $prices->count(),
                'lowest_price' => $prices->first(),
                'median_price' => $prices->median(),
                'highest_price' => $prices->last(),
                'lowest_listing_url' => $cheapest['url'] ?? null,
                'lowest_listing_title' => isset($cheapest['title']) ? mb_substr($cheapest['title'], 0, 255) : null,
            ],
        );

        $this->watch->update(['last_checked_at' => now()]);
    }
}
