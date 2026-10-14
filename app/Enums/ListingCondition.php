<?php

namespace App\Enums;

enum ListingCondition: string
{
    case Any = 'any';
    case New = 'new';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any condition',
            self::New => 'New / sealed',
            self::Used => 'Used',
        };
    }
}
