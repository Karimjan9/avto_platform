<?php

namespace App\Enums;

enum BusinessType: string
{
    case CarDealership = 'car_dealership';
    case PartsStore = 'parts_store';
    case MotoDealership = 'moto_dealership';

    public function section(): MarketplaceSection
    {
        return match ($this) {
            self::CarDealership => MarketplaceSection::Cars,
            self::PartsStore => MarketplaceSection::Parts,
            self::MotoDealership => MarketplaceSection::Moto,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CarDealership => 'Avtosalon',
            self::PartsStore => 'Zapchast do‘koni',
            self::MotoDealership => 'Motosalon',
        };
    }
}
