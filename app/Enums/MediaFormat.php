<?php

namespace App\Enums;

enum MediaFormat: string
{
    case Vhs = 'vhs';
    case Betamax = 'betamax';
    case LaserDisc = 'laserdisc';
    case Dvd = 'dvd';
    case BluRay = 'bluray';
    case UltraHd = 'uhd';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Vhs => 'VHS',
            self::Betamax => 'Betamax',
            self::LaserDisc => 'LaserDisc',
            self::Dvd => 'DVD',
            self::BluRay => 'Blu-ray',
            self::UltraHd => '4K UHD',
            self::Digital => 'Digital',
        };
    }

    /**
     * Physical formats can be bought used, so they are the only ones worth price tracking on eBay.
     */
    public function isPhysical(): bool
    {
        return $this !== self::Digital;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $format): array => ['value' => $format->value, 'label' => $format->label()],
            self::cases(),
        );
    }
}
