<?php

namespace App\Models;

use Database\Factories\PriceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $watch_id
 * @property Carbon $checked_on
 * @property int $listing_count
 * @property float|null $lowest_price
 * @property float|null $median_price
 * @property float|null $highest_price
 * @property string|null $lowest_listing_url
 * @property string|null $lowest_listing_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'checked_on', 'listing_count', 'lowest_price', 'median_price', 'highest_price',
    'lowest_listing_url', 'lowest_listing_title',
])]
class PriceSnapshot extends Model
{
    /** @use HasFactory<PriceSnapshotFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'lowest_price' => 'float',
            'median_price' => 'float',
            'highest_price' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Watch, $this>
     */
    public function watch(): BelongsTo
    {
        return $this->belongsTo(Watch::class);
    }
}
