<?php

namespace App\Models;

use App\Enums\CreditRole;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tmdb_id
 * @property string $name
 * @property string|null $profile_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $directed_count Only present after withCount('directed')
 * @property-read int $owned_count Only present after withCount('directed as owned_count')
 * @property-read Credit $pivot The credit row when loaded through a credits relation
 */
#[Fillable(['tmdb_id', 'name', 'profile_path'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Movie, $this, Credit>
     */
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'credits')
            ->using(Credit::class)
            ->withPivot(['role', 'character', 'order']);
    }

    /**
     * @return BelongsToMany<Movie, $this, Credit>
     */
    public function directed(): BelongsToMany
    {
        return $this->movies()->wherePivot('role', CreditRole::Director->value);
    }

    /**
     * People credited as director on at least one movie.
     *
     * @param  Builder<Person>  $query
     */
    #[Scope]
    protected function directors(Builder $query): void
    {
        $query->whereHas('directed');
    }
}
