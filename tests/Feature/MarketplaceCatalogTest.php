<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class MarketplaceCatalogTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_admin_catalog_flows_into_listing_form_and_checks_vehicle_year(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/admin/catalog/vehicles/makes', ['name' => 'TestMake'])->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);
        $make = $this->postJson('/api/v1/admin/catalog/vehicles/makes', ['name' => 'TestMake'])->assertCreated()->json('data.id');
        $model = $this->postJson('/api/v1/admin/catalog/vehicles/models', ['vehicle_make_id' => $make, 'name' => 'TestModel', 'section' => 'cars'])->assertCreated()->json('data.id');
        $generation = $this->postJson('/api/v1/admin/catalog/vehicles/generations', ['vehicle_model_id' => $model, 'name' => 'II', 'year_from' => 2020, 'year_to' => 2026])->assertCreated()->json('data.id');
        $variant = $this->postJson('/api/v1/admin/catalog/vehicles/variants', ['vehicle_generation_id' => $generation, 'name' => 'Comfort', 'fuel_type' => 'gasoline', 'engine_cc' => 1500])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/catalog/vehicles/models?section=cars&vehicle_make_id='.$make)->assertOk()->assertJsonPath('data.0.id', $model);
        $form = $this->getJson('/api/v1/catalog/form?section=cars&vehicle_variant_id='.$variant)->assertOk()->json('data');
        $this->assertSame('TestMake', $form['prefill']['details']['brand']);
        $this->assertContains('details.fuel_type', $form['read_only_fields']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/listings', $this->payload(['section' => 'cars', 'vehicle_variant_id' => $variant, 'details' => ['year' => 2019]]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_variant_id');
        $this->postJson('/api/v1/me/listings', $this->payload(['section' => 'cars', 'condition' => 'used', 'vehicle_variant_id' => $variant, 'details' => ['year' => 2024]]))->assertCreated()->assertJsonPath('data.details.brand', 'TestMake')->assertJsonPath('data.details.engine_cc', 1500);
        $this->postJson('/api/v1/me/listings', $this->payload(['section' => 'cars', 'vehicle_variant_id' => $variant, 'details' => ['year' => 2024, 'brand' => 'Fake']]))->assertUnprocessable()->assertJsonValidationErrors('details.brand');
    }

    public function test_one_product_compares_five_shops_without_merging_different_manufacturers(): void
    {
        $product = $this->product();
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $offer = $this->offer($this->company(), $product, $i + 1, (string) (10 + $i));
            $ids[] = $offer->id;
            if ($i === 0) {
                $offer->partDetails()->update(['unit' => 'set', 'quantity_per_set' => 4, 'origin' => 'aftermarket', 'warranty_months' => 12, 'return_days' => 14, 'return_terms' => 'Qadoq saqlangan bo‘lsin']);
            }
        }
        $this->getJson('/api/v1/parts/'.$product->id)->assertOk()->assertJsonPath('data.offers_count', 5);
        $this->getJson('/api/v1/parts/'.$product->id.'/offers?currency=USD&sort=price_asc')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('data.0.details.quantity_per_set', 4)->assertJsonPath('data.0.details.warranty_months', 12)->assertJsonPath('data.0.details.origin', 'aftermarket')->assertJsonMissingPath('data.0.business.moderated_by');
        $this->getJson('/api/v1/parts/'.$product->id.'/offers?sort=price_asc')->assertUnprocessable()->assertJsonValidationErrors('currency');
        Sanctum::actingAs(User::factory()->create());
        $proposal = ['title' => 'Taklif qilingan detal', 'manufacturer' => 'DifferentMaker', 'part_number' => $product->part_number, 'category' => 'other', 'attributes' => []];
        $new = $this->postJson('/api/v1/part-products', $proposal)->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->assertNotSame($product->id, $new);
        $this->postJson('/api/v1/part-products', $proposal)->assertOk()->assertJsonPath('data.id', $new);
        $this->getJson('/api/v1/parts/'.$new)->assertNotFound();
    }

    public function test_moderated_product_fitment_and_selected_vehicle_year_limit_offers(): void
    {
        $variant = $this->variant();
        $seller = User::factory()->create();
        Sanctum::actingAs($seller);
        $data = ['title' => 'Chap old fara', 'manufacturer' => 'Maker', 'part_number' => 'AB-123', 'category' => 'headlights', 'attributes' => ['side' => 'left', 'position' => 'front'], 'fitments' => [['vehicle_variant_id' => $variant->id, 'year_from' => 2020, 'year_to' => 2025]]];
        $id = $this->postJson('/api/v1/part-products', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/listings', $this->payload(['part_product_id' => $id]))->assertUnprocessable()->assertJsonValidationErrors('part_product_id');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson('/api/v1/admin/part-products/'.$id.'/moderation', ['status' => 'active'])->assertOk();
        Sanctum::actingAs($seller);
        $offerId = $this->postJson('/api/v1/me/listings', $this->payload(['part_product_id' => $id, 'details' => ['origin' => 'original', 'stock_quantity' => 5]]))->assertCreated()->assertJsonPath('data.details.part_number', 'AB-123')->assertJsonPath('data.details.side', 'left')->json('data.id');
        $this->postJson('/api/v1/me/listings', $this->payload(['part_product_id' => $id, 'details' => ['origin' => 'original', 'manufacturer' => 'Wrong']]))->assertUnprocessable()->assertJsonValidationErrors('details.manufacturer');
        $this->getJson('/api/v1/parts?vehicle_variant_id='.$variant->id.'&year=2024')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/parts?vehicle_variant_id='.$variant->id.'&year=2018')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/parts?part_number=ab123')->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotNull(Listing::find($offerId)->part_product_id);
    }

    public function test_customer_vehicle_storage_is_not_available(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/garage')->assertNotFound();
        $this->postJson('/api/v1/me/garage', [])->assertNotFound();
        $this->assertFalse(Schema::hasTable('garage_vehicles'));
    }
}
