<?php

namespace App\Models;

use App\Enums\MediaFormat;
use Database\Factories\CopyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $movie_id
 * @property MediaFormat $format
 * @property string|null $edition
 * @property string|null $condition
 * @property Carbon|null $acquired_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['movie_id', 'format', 'edition', 'condition', 'acquired_on', 'notes'])]
class Copy extends Model
{
    /** @use HasFactory<CopyFactory> */
    use HasFactory;

    /**
     * Owning a copy ends the hunt: matching want-list entries (same format, or any format) stop being priced.
     */
    protected static function booted(): void
    {
        static::created(function (Copy $copy): void {
            Watch::query()
                ->where('user_id', $copy->user_id)
                ->where('movie_id', $copy->movie_id)
                ->where(fn ($query) => $query->whereNull('format')->orWhere('format', $copy->format))
                ->update(['active' => false]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => MediaFormat::class,
            'acquired_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Movie, $this>
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
