<?php

namespace App\Http\Resources;

use App\Models\Copy;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The slim shape a movie needs to render as a VHS spine or clamshell on a shelf.
 *
 * @mixin Movie
 */
class MovieCardResource extends JsonResource
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
            'title' => $this->title,
            'year' => $this->release_year,
            'poster_path' => $this->poster_path,
            'vote_average' => $this->vote_average,
            'owned_formats' => $this->whenLoaded(
                'copies',
                fn () => $this->copies->map(fn (Copy $copy): string => $copy->format->value)->unique()->values()->all(),
            ),
            'directors' => $this->whenLoaded(
                'directors',
                fn () => $this->directors->map(fn (Person $person): array => ['id' => $person->id, 'name' => $person->name])->all(),
            ),
        ];
    }
}
