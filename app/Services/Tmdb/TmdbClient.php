<?php

namespace App\Services\Tmdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TmdbClient
{
    /**
     * TMDB's discover endpoint refuses pages beyond this, so imports are sliced by year to stay under it.
     */
    public const int MaxDiscoverPage = 500;

    public const int HorrorGenreId = 27;

    /**
     * One page of movies in a genre released in a given year.
     *
     * @return array{page: int, total_pages: int, results: list<array<string, mixed>>}
     */
    public function discoverByGenre(int $genreId, int $year, int $page = 1): array
    {
        $payload = $this->request()
            ->get('discover/movie', [
                'with_genres' => $genreId,
                'primary_release_year' => $year,
                'include_adult' => 'false',
                'include_video' => 'false',
                'sort_by' => 'popularity.desc',
                'page' => $page,
            ])
            ->throw()
            ->json();

        return [
            'page' => (int) $payload['page'],
            'total_pages' => min((int) $payload['total_pages'], self::MaxDiscoverPage),
            'results' => $payload['results'] ?? [],
        ];
    }

    /**
     * Full movie details with credits and keywords, or null when TMDB no longer has the movie.
     *
     * @return array<string, mixed>|null
     */
    public function movieDetails(int $tmdbId): ?array
    {
        $response = $this->request()->get("movie/{$tmdbId}", [
            'append_to_response' => 'credits,keywords',
        ]);

        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('services.tmdb.base_url'))
            ->withToken((string) config('services.tmdb.token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(
                3,
                fn (int $attempt, Throwable $exception): int => $this->retryDelay($attempt, $exception),
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests())),
                throw: false,
            );
    }

    /**
     * Milliseconds to wait before retrying, honoring TMDB's Retry-After header on 429s.
     */
    protected function retryDelay(int $attempt, Throwable $exception): int
    {
        if ($exception instanceof RequestException && $exception->response->tooManyRequests()) {
            return max(1, (int) $exception->response->header('Retry-After')) * 1000;
        }

        return $attempt * 500;
    }
}
