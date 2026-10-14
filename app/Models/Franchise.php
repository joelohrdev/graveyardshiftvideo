<?php

namespace App\Models;

use Database\Factories\FranchiseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A TMDB collection: a series of related movies such as "Friday the 13th Collection".
 *
 * @property int $id
 * @property int $tmdb_id
 * @property string $name
 * @property string|null $overview
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property Carbon|null $parts_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $movies_count Only present after withCount('movies')
 * @property-read int $owned_count Only present after withCount('movies as owned_count')
 */
#[Fillable(['tmdb_id', 'name', 'overview', 'poster_path', 'backdrop_path', 'parts_synced_at'])]
class Franchise extends Model
{
    /** @use HasFactory<FranchiseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parts_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Movie, $this>
     */
    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class);
    }

    /**
     * The name without TMDB's " Collection" suffix: "Friday the 13th Collection" becomes "Friday the 13th".
     */
    public function displayName(): string
    {
        return (string) preg_replace('/\s+collection$/i', '', $this->name);
    }
}
