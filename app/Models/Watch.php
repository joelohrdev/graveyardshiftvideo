<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use Database\Factories\WatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $movie_id
 * @property MediaFormat|null $format
 * @property ListingCondition $condition
 * @property float|null $target_price
 * @property bool $active
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['movie_id', 'format', 'condition', 'target_price', 'active', 'last_checked_at'])]
class Watch extends Model
{
    /** @use HasFactory<WatchFactory> */
    use HasFactory;

    /**
     * Daily snapshots needed before a price can be called a deal relative to its own history.
     */
    public const int MinimumHistory = 7;

    /**
     * A lowest price at or below this share of the trailing median counts as a good deal.
     */
    public const float GoodDealRatio = 0.8;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'condition' => 'any',
        'active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => MediaFormat::class,
            'condition' => ListingCondition::class,
            'target_price' => 'float',
            'active' => 'boolean',
            'last_checked_at' => 'datetime',
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

    /**
     * @return HasMany<PriceSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    /**
     * @return HasOne<PriceSnapshot, $this>
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(PriceSnapshot::class)->latestOfMany('checked_on');
    }

    /**
     * @param  Builder<Watch>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Judge today's price against the target and the trailing 30 days of daily lows.
     *
     * Expects the `snapshots` relation to be loaded (or loads it), newest first is not required.
     */
    public function dealStatus(): DealStatus
    {
        $snapshots = $this->snapshots
            ->filter(fn (PriceSnapshot $snapshot): bool => $snapshot->lowest_price !== null)
            ->sortBy('checked_on')
            ->values();

        $latest = $snapshots->last();

        if ($latest === null) {
            return DealStatus::Wait;
        }

        if ($this->target_price !== null && $latest->lowest_price <= $this->target_price) {
            return DealStatus::Steal;
        }

        $history = $snapshots->filter(
            fn (PriceSnapshot $snapshot): bool => $snapshot->checked_on->gte($latest->checked_on->copy()->subDays(30)),
        );

        if ($history->count() < self::MinimumHistory) {
            return DealStatus::Wait;
        }

        $trailingMedian = $history->median('lowest_price');

        if ($latest->lowest_price <= $trailingMedian * self::GoodDealRatio) {
            return DealStatus::GoodDeal;
        }

        return DealStatus::Hold;
    }
}
