<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\MarketplaceSection;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'section' => MarketplaceSection::Cars,
            'title' => 'Sotiladigan avtomobil', 'description' => 'Avtomobil haqida batafsil ma’lumot.',
            'condition' => 'used', 'offer_type' => 'sale', 'price' => '15000.00', 'currency' => 'USD',
            'city' => 'Buxoro', 'contact_phone' => '+998901234567', 'status' => ListingStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ListingStatus::Published, 'published_at' => now()]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Listing $listing) {
            $attributes = match ($listing->section) {
                MarketplaceSection::Cars => ['brand' => 'TestBrand', 'model' => 'TestModel', 'year' => 2024],
                MarketplaceSection::Parts => ['part_number' => 'PART-001'],
                MarketplaceSection::Moto => ['type' => 'motorcycle', 'brand' => 'TestBrand', 'model' => 'TestMoto', 'year' => 2023],
            };
            $listing->{$listing->detailRelation()}()->create($attributes);
        });
    }
}
