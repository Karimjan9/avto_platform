<?php

namespace Tests\Support;

use App\Models\Business;
use App\Models\Listing;
use App\Models\PartProduct;
use App\Models\User;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Support\Str;

trait MarketplaceFixtures
{
    protected function variant(string $section = 'cars'): VehicleVariant
    {
        $make = VehicleMake::firstOrCreate(['name' => 'FixtureMake']);
        $model = VehicleModel::firstOrCreate(['vehicle_make_id' => $make->id, 'section' => $section, 'name' => 'Fixture-'.$section]);
        $generation = VehicleGeneration::firstOrCreate(['vehicle_model_id' => $model->id, 'name' => 'I'], ['year_from' => 2010]);

        return VehicleVariant::firstOrCreate(['vehicle_generation_id' => $generation->id, 'name' => '1.5'], ['engine_cc' => 1500, 'fuel_type' => 'gasoline', 'power_kw' => 90]);
    }

    protected function company(?User $owner = null, array $sections = ['parts']): Business
    {
        $owner ??= User::factory()->create();
        $type = ['cars' => 'car_dealership', 'parts' => 'parts_store', 'moto' => 'moto_dealership'][$sections[0]];
        $business = Business::factory()->for($owner)->create(['type' => $type]);
        foreach ($sections as $section) {
            $business->directions()->create(['section' => $section]);
        }

        return $business->fresh();
    }

    protected function product(?User $author = null): PartProduct
    {
        $author ??= User::factory()->create();
        $number = 'P-'.Str::upper(Str::random(8));

        return PartProduct::create(['created_by' => $author->id, 'identity_key' => hash('sha256', $number), 'title' => 'Sinov ehtiyot qismi', 'manufacturer' => 'FixtureMaker', 'part_number' => $number, 'article_key' => PartProduct::articleKey($number), 'category' => 'other', 'attributes' => [], 'status' => 'active']);
    }

    protected function offer(Business $business, ?PartProduct $product = null, int $stock = 10, string $price = '12.34'): Listing
    {
        $product ??= $this->product($business->user);
        $listing = Listing::factory()->published()->create(['user_id' => $business->user_id, 'business_id' => $business->id, 'section' => 'parts', 'part_product_id' => $product->id, 'currency' => 'USD', 'price' => $price]);
        $listing->partDetails()->update($product->detailDefaults() + ['stock_quantity' => $stock, 'unit' => 'piece', 'origin' => 'original', 'delivery_available' => true, 'pickup_available' => true]);
        $detail = $listing->partDetails;
        $detail->stock_updated_at = now();
        $detail->stock_version = 1;
        $detail->save();

        return $listing->fresh();
    }

    protected function payload(array $changes = []): array
    {
        return array_replace(['section' => 'parts', 'title' => 'Yangi ehtiyot qismi', 'description' => 'Batafsil texnik ma’lumotlar mavjud.', 'condition' => 'new', 'offer_type' => 'sale', 'price' => '12.34', 'currency' => 'USD', 'city' => 'Buxoro', 'contact_phone' => '+998901234567', 'details' => ['stock_quantity' => 10]], $changes);
    }
}
