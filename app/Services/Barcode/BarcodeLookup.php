<?php

namespace App\Services\Barcode;

use App\Enums\MediaFormat;
use App\Services\Ebay\EbayClient;
use Illuminate\Support\Collection;

/**
 * Works out which movie and format a case's barcode belongs to, from the titles
 * of eBay listings for that exact product.
 */
class BarcodeLookup
{
    /**
     * Listing noise that isn't part of the movie's title.
     *
     * @var list<string>
     */
    public const array Noise = [
        'vhs', 'betamax', 'beta', 'laserdisc', 'laser disc', 'dvd', 'blu ray', 'bluray', '4k', 'uhd', 'ultra hd',
        'digital', 'hd', 'widescreen', 'full screen', 'fullscreen', 'special edition', 'collectors edition',
        'collector s edition', 'limited edition', 'deluxe edition', 'anniversary edition', 'unrated', 'uncut',
        'remastered', 'restored', 'rare', 'oop', 'out of print', 'htf', 'factory sealed', 'sealed', 'brand new', 'new',
        'clamshell', 'big box', 'ex rental', 'rental', 'tape', 'movie', 'film', 'horror', 'cult', 'classic', 'ntsc',
        'pal', 'region free', 'slipcover', 'steelbook', 'w slipcover', 'with slipcover', 'combo', 'disc', 'discs',
    ];

    public function __construct(protected EbayClient $ebay) {}

    /**
     * @return array{title: string|null, year: int|null, format: MediaFormat|null, listings: list<string>}
     */
    public function lookup(string $barcode): array
    {
        $listings = $this->ebay->listingsForBarcode($barcode);
        $titles = array_column($listings, 'title');

        return [
            'title' => $this->title($titles),
            'year' => $this->year($titles),
            'format' => $this->format($listings),
            'listings' => array_slice($titles, 0, 3),
        ];
    }

    /**
     * The cleaned title most listings agree on, shortest first when they tie.
     *
     * @param  list<string>  $titles
     */
    public function title(array $titles): ?string
    {
        $cleaned = collect($titles)
            ->map(fn (string $title): string => $this->clean($title))
            ->filter(fn (string $title): bool => mb_strlen($title) >= 2);

        if ($cleaned->isEmpty()) {
            return null;
        }

        /** @var Collection<string, int> $counts */
        $counts = $cleaned->countBy();

        return $counts
            ->keys()
            ->sortBy(fn (string $title): array => [-$counts[$title], mb_strlen($title)])
            ->first();
    }

    /**
     * Strip formats, edition blurbs, years and bracketed asides, leaving the movie's name.
     */
    public function clean(string $title): string
    {
        $title = (string) preg_replace('/[\(\[\{].*?[\)\]\}]/u', ' ', $title);
        $title = str_replace(["'", '’'], ' ', mb_strtolower($title));
        $title = (string) preg_replace('/\b(19|20)\d{2}\b/u', ' ', $title);
        $title = (string) preg_replace('/[^\p{L}\p{N}&:]+/u', ' ', $title);

        foreach (self::Noise as $phrase) {
            $title = (string) preg_replace('/\b'.preg_quote($phrase, '/').'\b/u', ' ', $title);
        }

        $title = trim((string) preg_replace('/\s+/u', ' ', $title), ' :&');

        return mb_convert_case($title, MB_CASE_TITLE);
    }

    /**
     * The release year most listings mention, if any.
     *
     * @param  list<string>  $titles
     */
    public function year(array $titles): ?int
    {
        $latest = (int) now()->year;

        $year = collect($titles)
            ->flatMap(fn (string $title): array => preg_match_all('/\b((?:19|20)\d{2})\b/', $title, $matches) ? $matches[1] : [])
            ->map(fn (string $year): int => (int) $year)
            ->filter(fn (int $year): bool => $year >= 1920 && $year <= $latest)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $year !== null ? (int) $year : null;
    }

    /**
     * The physical format most listings point to, from their titles and eBay categories.
     *
     * @param  list<array{title: string, categories: list<string>}>  $listings
     */
    public function format(array $listings): ?MediaFormat
    {
        $votes = collect($listings)
            ->map(function (array $listing): ?MediaFormat {
                $text = mb_strtolower($listing['title'].' '.implode(' ', $listing['categories']));

                return match (true) {
                    (bool) preg_match('/\b(4k|uhd|ultra hd)\b/', $text) => MediaFormat::UltraHd,
                    (bool) preg_match('/\bblu[- ]?ray\b/', $text) => MediaFormat::BluRay,
                    (bool) preg_match('/\blaser ?disc\b/', $text) => MediaFormat::LaserDisc,
                    (bool) preg_match('/\bbeta(max)?\b/', $text) => MediaFormat::Betamax,
                    (bool) preg_match('/\bvhs\b/', $text) => MediaFormat::Vhs,
                    (bool) preg_match('/\bdvds?\b/', $text) => MediaFormat::Dvd,
                    default => null,
                };
            })
            ->filter()
            ->map(fn (MediaFormat $format): string => $format->value)
            ->countBy()
            ->sortDesc();

        $winner = $votes->keys()->first();

        return $winner !== null ? MediaFormat::from($winner) : null;
    }
}
