<?php

namespace App\Models;

use Database\Factories\ExcludedMovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A TMDB movie removed from the store on purpose, so imports never bring it back.
 *
 * @property int $id
 * @property int $tmdb_id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tmdb_id', 'title'])]
class ExcludedMovie extends Model
{
    /** @use HasFactory<ExcludedMovieFactory> */
    use HasFactory;
}
