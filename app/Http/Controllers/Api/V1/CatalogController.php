<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceSection;

class CatalogController extends Controller
{
    public function __invoke()
    {
        return response()->json(['data' => [
            'sections' => MarketplaceSection::where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
            'conditions' => config('marketplace.conditions'), 'offer_types' => config('marketplace.offer_types'),
            'rental_sections' => ['cars'], 'rental_periods' => config('marketplace.rental_periods'),
            'business_types' => collect(BusinessType::cases())->mapWithKeys(fn (BusinessType $type) => [$type->value => ['name' => $type->label(), 'section' => $type->section()->value]]),
            'seller_types' => ['private' => 'Shaxsiy sotuvchi'] + collect(BusinessType::cases())->mapWithKeys(fn (BusinessType $type) => [$type->value => $type->label()])->all(),
            'deal_terms' => config('marketplace.deal_terms'),
            'part_categories' => config('marketplace.part_categories'), 'part_origins' => config('marketplace.part_origins'),
            'part_units' => config('marketplace.part_units'), 'tyre_seasons' => config('marketplace.tyre_seasons'),
            'part_sides' => config('marketplace.part_sides'), 'part_positions' => config('marketplace.part_positions'),
            'technical_conditions' => config('marketplace.technical_conditions'), 'gas_equipment' => config('marketplace.gas_equipment'),
            'charging_types' => config('marketplace.charging_types'), 'driver_options' => config('marketplace.driver_options'),
            'moto_transmissions' => config('marketplace.moto_transmissions'),
            'form_endpoint' => '/api/v1/catalog/form',
            'moto_types' => config('marketplace.moto_types'), 'currencies' => config('marketplace.currencies'),
            'fuel_types' => config('marketplace.fuel_types'), 'moto_fuel_types' => ['gasoline', 'electric'],
            'transmissions' => config('marketplace.transmissions'), 'body_types' => config('marketplace.body_types'),
            'drive_types' => config('marketplace.drive_types'), 'max_images' => config('marketplace.max_images'),
        ]]);
    }
}
