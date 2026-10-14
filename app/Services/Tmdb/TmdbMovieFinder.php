<?php

namespace App\Services\Tmdb;

use Illuminate\Support\Str;

/**
 * Turns what a collector types ("The Beyond", "Lucio Fulci's The Beyond",
 * "Fulci zombi", "Lucio Fulci") into TMDB movie results.
 */
class TmdbMovieFinder
{
    /**
     * Longest run of words treated as a person's name when looking for a director.
     */
    public const int MaxNameWords = 3;

    public const int MaxResults = 20;

    public function __construct(protected TmdbClient $tmdb) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function find(string $query, ?int $year = null): array
    {
        $query = trim($query);

        $results = $this->tmdb->searchMovies($query, $year);

        if ($results !== []) {
            return $results;
        }

        // "Lucio Fulci's The Beyond": TMDB titles rarely carry the possessive credit.
        $withoutCredit = $this->withoutPossessiveCredit($query);

        if ($withoutCredit !== null) {
            $results = $this->tmdb->searchMovies($withoutCredit, $year);

            if ($results !== []) {
                return $results;
            }
        }

        return $this->byPerson($query, $year);
    }

    /**
     * The title part of a "Director's Title" search, or null when there's no possessive credit.
     */
    public function withoutPossessiveCredit(string $query): ?string
    {
        if (! preg_match("/^.+?['’]s\\s+(.{2,})$/u", $query, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * Find a person's name at the start or end of the query ("Lucio Fulci zombi", "zombi fulci")
     * and return their movies whose titles contain the remaining words.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function byPerson(string $query, ?int $year): array
    {
        $words = $this->words(str_replace(["'s ", '’s '], ' ', $query.' '));

        foreach ($this->nameSplits($words) as [$name, $rest]) {
            $person = $this->person($name);

            if ($person === null) {
                continue;
            }

            return $this->moviesOf($person, $rest, $year);
        }

        return [];
    }

    /**
     * Candidate [name words, title words] splits, longest name first, name at the start then the end.
     *
     * @param  list<string>  $words
     * @return list<array{0: list<string>, 1: list<string>}>
     */
    protected function nameSplits(array $words): array
    {
        $splits = [];

        for ($length = min(self::MaxNameWords, count($words)); $length >= 1; $length--) {
            $splits[] = [array_slice($words, 0, $length), array_slice($words, $length)];

            if ($length < count($words)) {
                $splits[] = [array_slice($words, -$length), array_slice($words, 0, -$length)];
            }
        }

        return $splits;
    }

    /**
     * The most popular person whose name contains every one of the given words.
     *
     * @param  list<string>  $nameWords
     * @return array<string, mixed>|null
     */
    protected function person(array $nameWords): ?array
    {
        // A lone short word ("the", "of") is not worth a person search.
        if (count($nameWords) === 1 && mb_strlen($nameWords[0]) < 4) {
            return null;
        }

        foreach ($this->tmdb->searchPeople(implode(' ', $nameWords)) as $person) {
            $name = $this->words((string) ($person['name'] ?? ''));

            if (array_diff($nameWords, $name) === []) {
                return $person;
            }
        }

        return null;
    }

    /**
     * A person's movies in release order (directing and acting), filtered to titles containing every remaining word.
     *
     * @param  array<string, mixed>  $person
     * @param  list<string>  $titleWords
     * @return array<int, array<string, mixed>>
     */
    protected function moviesOf(array $person, array $titleWords, ?int $year): array
    {
        $credits = $this->tmdb->personMovieCredits((int) $person['id']);

        return collect($credits['crew'])
            ->where('job', 'Director')
            ->concat($credits['cast'])
            ->unique('id')
            ->filter(function (array $movie) use ($titleWords, $year): bool {
                $title = implode(' ', $this->words(($movie['title'] ?? '').' '.($movie['original_title'] ?? '')));

                foreach ($titleWords as $word) {
                    if (! str_contains($title, $word)) {
                        return false;
                    }
                }

                return $year === null || str_starts_with((string) ($movie['release_date'] ?? ''), (string) $year);
            })
            ->sortBy(fn (array $movie): string => filled($movie['release_date'] ?? null) ? $movie['release_date'] : '9999')
            ->take(self::MaxResults)
            ->values()
            ->all();
    }

    /**
     * Lowercase ASCII words, so "Fulci" matches "fulci" and "Zombi 2" splits cleanly.
     *
     * @return list<string>
     */
    protected function words(string $text): array
    {
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)));

        return array_values(array_filter(explode(' ', $normalized), fn (string $word): bool => $word !== ''));
    }
}
