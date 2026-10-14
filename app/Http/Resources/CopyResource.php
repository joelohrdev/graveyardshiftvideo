<?php

namespace App\Http\Resources;

use App\Models\Copy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Copy
 */
class CopyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'edition' => $this->edition,
            'condition' => $this->condition,
            'acquired_on' => $this->acquired_on?->toDateString(),
            'notes' => $this->notes,
            'movie' => $this->whenLoaded('movie', fn (): array => MovieCardResource::make($this->movie)->resolve()),
        ];
    }
}
