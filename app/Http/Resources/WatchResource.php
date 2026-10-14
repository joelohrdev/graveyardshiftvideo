<?php

namespace App\Http\Resources;

use App\Models\PriceSnapshot;
use App\Models\Watch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A want-list entry with its price history and buy signal. Expects `snapshots` to be loaded.
 *
 * @mixin Watch
 */
class WatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshots = $this->snapshots->sortBy('checked_on')->values();
        $latest = $snapshots->last();
        $dealStatus = $this->dealStatus();

        return [
            'id' => $this->id,
            'format' => $this->format?->value,
            'format_label' => $this->format?->label() ?? 'Any format',
            'condition' => $this->condition->value,
            'condition_label' => $this->condition->label(),
            'target_price' => $this->target_price,
            'active' => $this->active,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'deal_status' => $dealStatus->value,
            'deal_label' => $dealStatus->label(),
            'latest' => $latest === null ? null : [
                'checked_on' => $latest->checked_on->toDateString(),
                'listing_count' => $latest->listing_count,
                'lowest_price' => $latest->lowest_price,
                'median_price' => $latest->median_price,
                'highest_price' => $latest->highest_price,
                'lowest_listing_url' => $latest->lowest_listing_url,
                'lowest_listing_title' => $latest->lowest_listing_title,
            ],
            'history' => $snapshots->map(fn (PriceSnapshot $snapshot): array => [
                'date' => $snapshot->checked_on->toDateString(),
                'lowest' => $snapshot->lowest_price,
                'median' => $snapshot->median_price,
            ])->all(),
            'movie' => $this->whenLoaded('movie', fn (): array => MovieCardResource::make($this->movie)->resolve()),
        ];
    }
}
