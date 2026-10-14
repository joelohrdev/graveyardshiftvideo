<?php

namespace App\Enums;

enum DealStatus: string
{
    case Steal = 'steal';
    case GoodDeal = 'good_deal';
    case Hold = 'hold';
    case Wait = 'wait';

    public function label(): string
    {
        return match ($this) {
            self::Steal => 'Steal!',
            self::GoodDeal => 'Good deal',
            self::Hold => 'Hold',
            self::Wait => 'Wait',
        };
    }

    /**
     * Lower ranks sort first so the best buys float to the top of the want list.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Steal => 0,
            self::GoodDeal => 1,
            self::Hold => 2,
            self::Wait => 3,
        };
    }
}
