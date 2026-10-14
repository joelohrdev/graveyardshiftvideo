<?php

namespace App\Services\Tmdb;

use App\Models\ExcludedMovie;
use App\Models\Movie;

/**
 * Writes TMDB list results (discover pages, franchise parts) into the movies table.
 */
class CatalogWriter
{
    /**
     * Upsert the basic movie rows, skipping anything removed from the store on purpose.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<int> TMDB ids that were written
     */
    public function upsertResults(array $results, ?int $fallbackYear = null): array
    {
        $excluded = ExcludedMovie::query()
            ->whereIn('tmdb_id', array_column($results, 'id'))
            ->pluck('tmdb_id')
            ->all();

        $rows = [];

        foreach ($results as $result) {
            if (! in_array((int) $result['id'], $excluded, true)) {
                $rows[] = $this->row($result, $fallbackYear);
            }
        }

        if ($rows === []) {
            return [];
        }

        Movie::upsert($rows, ['tmdb_id'], array_keys(array_diff_key($rows[0], ['tmdb_id' => true, 'created_at' => true])));

        return array_column($rows, 'tmdb_id');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function row(array $result, ?int $fallbackYear): array
    {
        $releaseDate = filled($result['release_date'] ?? null) ? $result['release_date'] : null;

        return [
            'tmdb_id' => (int) $result['id'],
            'title' => (string) $result['title'],
            'sort_title' => Movie::sortableTitle((string) $result['title']),
            'original_title' => $result['original_title'] ?? null,
            'overview' => filled($result['overview'] ?? null) ? $result['overview'] : null,
            'release_date' => $releaseDate,
            'release_year' => $releaseDate !== null ? (int) substr($releaseDate, 0, 4) : $fallbackYear,
            'poster_path' => $result['poster_path'] ?? null,
            'backdrop_path' => $result['backdrop_path'] ?? null,
            'original_language' => $result['original_language'] ?? null,
            'vote_average' => (float) ($result['vote_average'] ?? 0),
            'vote_count' => (int) ($result['vote_count'] ?? 0),
            'popularity' => (float) ($result['popularity'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
