<?php

namespace Database\Seeders;

use App\Enums\MarketplaceSection;
use App\Models\MarketplaceSection as Section;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MarketplaceSection::cases() as $index => $section) {
            Section::updateOrCreate(['code' => $section->value], ['name' => $section->label(), 'sort_order' => $index + 1]);
        }
    }
}
