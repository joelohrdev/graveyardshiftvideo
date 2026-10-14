<?php

namespace App\Services\Ebay;

use App\Enums\MediaFormat;
use Illuminate\Support\Str;

/**
 * Throws out eBay results that are not actually a copy of the movie in the wanted format:
 * other movies, lots, posters, empty cases and the wrong disc type.
 */
class ListingFilter
{
    /**
     * Phrases that mean the listing is not a single playable copy.
     *
     * @var list<string>
     */
    public const array Excluded = [
        'lot', 'lots', 'bundle', 'collection of', 'poster', 'case only', 'empty case', 'no disc', 'no tape',
        'cover only', 'artwork only', 'art only', 'sleeve only', 'slipcover only', 'replacement case',
        'press kit', 'lobby card', 'shirt', 'trailer', 'standee',
    ];

    /**
     * @param  list<array{title: string, price: float, url: string}>  $listings
     * @return list<array{title: string, price: float, url: string}>
     */
    public function filter(array $listings, string $movieTitle, ?MediaFormat $format): array
    {
        return array_values(array_filter(
            $listings,
            fn (array $listing): bool => $this->accepts($listing['title'], $movieTitle, $format),
        ));
    }

    public function accepts(string $listingTitle, string $movieTitle, ?MediaFormat $format): bool
    {
        $listing = $this->normalize($listingTitle);

        if (! preg_match('/\b'.preg_quote($this->normalize($movieTitle), '/').'\b/', $listing)) {
            return false;
        }

        foreach (self::Excluded as $phrase) {
            if (preg_match('/\b'.preg_quote($phrase, '/').'\b/', $listing)) {
                return false;
            }
        }

        return $this->matchesFormat($listing, $format);
    }

    protected function matchesFormat(string $listing, ?MediaFormat $format): bool
    {
        $isUltraHd = (bool) preg_match('/\b(4k|uhd|ultra hd)\b/', $listing);
        $isBluRay = (bool) preg_match('/\bblu ?ray\b/', $listing);

        return match ($format) {
            MediaFormat::Vhs => (bool) preg_match('/\bvhs\b/', $listing),
            MediaFormat::Betamax => (bool) preg_match('/\bbeta(max)?\b/', $listing),
            MediaFormat::LaserDisc => (bool) preg_match('/\blaser ?disc\b/', $listing),
            MediaFormat::UltraHd => $isUltraHd,
            MediaFormat::BluRay => $isBluRay && ! $isUltraHd,
            MediaFormat::Dvd => (bool) preg_match('/\bdvd\b/', $listing) && ! $isBluRay && ! $isUltraHd,
            default => true,
        };
    }

    /**
     * Lowercase ASCII with punctuation collapsed to single spaces, so "Blu-Ray" ≈ "blu ray" and "Dawn of the Dead!" ≈ "dawn of the dead".
     */
    protected function normalize(string $text): string
    {
        $text = Str::of($text)->ascii()->lower()->replace('&', ' and ')->replace("'", '');

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', (string) $text));
    }
}
