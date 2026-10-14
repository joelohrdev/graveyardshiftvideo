<?php

namespace App\Models;

use App\Enums\CreditRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A person's credit on a movie: director, or a billed cast member with their character.
 *
 * @property int $movie_id
 * @property int $person_id
 * @property CreditRole $role
 * @property string|null $character
 * @property int $order
 */
class Credit extends Pivot
{
    protected $table = 'credits';

    public $incrementing = true;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CreditRole::class,
        ];
    }
}
