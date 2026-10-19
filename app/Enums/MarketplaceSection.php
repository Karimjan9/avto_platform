<?php

namespace App\Enums;

enum MarketplaceSection: string
{
    case Cars = 'cars';
    case Parts = 'parts';
    case Moto = 'moto';

    public function label(): string
    {
        return match ($this) {
            self::Cars => 'Mashinalar',
            self::Parts => 'Zapchastlar',
            self::Moto => 'Moto',
        };
    }
}
