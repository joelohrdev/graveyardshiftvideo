<?php

namespace App\Models;

use App\Enums\CreditRole;
use Carbon\CarbonInterface;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tmdb_id
 * @property string $title
 * @property string|null $original_title
 * @property string|null $overview
 * @property Carbon|null $release_date
 * @property int|null $release_year
 * @property int|null $runtime
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property string|null $original_language
 * @property float $vote_average
 * @property int $vote_count
 * @property float $popularity
 * @property Carbon|null $details_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Credit $pivot The credit row when loaded through a credits relation
 */
#[Fillable([
    'tmdb_id', 'title', 'original_title', 'overview', 'release_date', 'release_year', 'runtime',
    'poster_path', 'backdrop_path', 'original_language', 'vote_average', 'vote_count', 'popularity',
    'details_synced_at',
])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'vote_average' => 'float',
            'popularity' => 'float',
            'details_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Person, $this, Credit>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'credits')
            ->using(Credit::class)
            ->withPivot(['role', 'character', 'order']);
    }

    /**
     * @return BelongsToMany<Person, $this, Credit>
     */
    public function directors(): BelongsToMany
    {
        return $this->people()->wherePivot('role', CreditRole::Director->value);
    }

    /**
     * @return BelongsToMany<Person, $this, Credit>
     */
    public function cast(): BelongsToMany
    {
        return $this->people()->wherePivot('role', CreditRole::Cast->value)->orderByPivot('order');
    }

    /**
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * @return BelongsToMany<Keyword, $this>
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class);
    }

    /**
     * @return HasMany<Copy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class);
    }

    /**
     * @return HasMany<Watch, $this>
     */
    public function watches(): HasMany
    {
        return $this->hasMany(Watch::class);
    }

    /**
     * Movies the given user has at least one copy of.
     *
     * @param  Builder<Movie>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereHas('copies', fn (Builder $copies) => $copies->whereBelongsTo($user));
    }

    /**
     * Eager load only the given user's copies, so cards can show which formats are on the shelf.
     *
     * @param  Builder<Movie>  $query
     */
    #[Scope]
    protected function withCopiesOf(Builder $query, User $user): void
    {
        $query->with(['copies' => fn (HasMany $copies) => $copies->whereBelongsTo($user)->orderBy('format')]);
    }

    /**
     * Movies whose cached TMDB details are older than the given cutoff (or were never fetched).
     *
     * @param  Builder<Movie>  $query
     */
    #[Scope]
    protected function staleSince(Builder $query, CarbonInterface $cutoff): void
    {
        $query->where(fn (Builder $stale) => $stale
            ->whereNull('details_synced_at')
            ->orWhere('details_synced_at', '<', $cutoff));
    }
}
