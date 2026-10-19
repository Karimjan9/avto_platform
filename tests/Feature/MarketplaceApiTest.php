<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\ListingStatus;
use App\Enums\MarketplaceSection;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function car(array $overrides = []): array
    {
        return array_replace([
            'section' => 'cars', 'title' => 'Avtomobil sotiladi', 'description' => 'Avtomobil yaxshi holatda, barcha ma’lumotlar mavjud.',
            'condition' => 'used', 'offer_type' => 'sale', 'price' => '15000.25', 'currency' => 'USD',
            'city' => 'Buxoro', 'contact_phone' => '+998901234567',
            'details' => ['brand' => 'TestBrand', 'model' => 'TestModel', 'year' => 2024, 'mileage_km' => 15000],
        ], $overrides);
    }

    public function test_catalog_contains_exactly_three_sections_and_no_bicycles(): void
    {
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonCount(3, 'data.sections')
            ->assertJsonPath('data.sections.0.code', 'cars')->assertJsonPath('data.sections.1.code', 'parts')
            ->assertJsonPath('data.sections.2.code', 'moto')->assertJsonPath('data.rental_sections', ['cars'])
            ->assertJsonMissingPath('data.moto_types.bicycle');
    }

    public function test_new_used_and_rental_cars_are_saved_with_typed_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        foreach (['new', 'used'] as $condition) {
            $this->postJson('/api/v1/me/listings', $this->car(['condition' => $condition]))
                ->assertCreated()->assertJsonPath('data.condition', $condition)->assertJsonPath('data.status', 'draft')
                ->assertJsonPath('data.price', '15000.25')->assertJsonPath('data.details.year', 2024);
        }
        $this->postJson('/api/v1/me/listings', $this->car(['offer_type' => 'rent', 'rental_period' => 'day', 'rental' => ['deposit' => '0', 'min_days' => 1, 'driver_option' => 'without_driver'], 'submit' => true]))
            ->assertCreated()->assertJsonPath('data.offer_type', 'rent')->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseCount('car_details', 3);
        $this->assertDatabaseCount('part_details', 0);
    }

    public function test_parts_and_motorized_two_wheelers_have_separate_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/listings', $this->car(['section' => 'parts', 'details' => ['part_number' => 'P-001', 'compatible_brands' => ['TestBrand']]]))
            ->assertCreated()->assertJsonPath('data.details.part_number', 'P-001');
        $this->postJson('/api/v1/me/listings', $this->car(['section' => 'moto', 'details' => ['type' => 'scooter', 'brand' => 'TestBrand', 'model' => 'Scooter', 'year' => 2025, 'fuel_type' => 'electric', 'power_kw' => 3, 'battery_voltage_v' => 72, 'battery_capacity_ah' => 30, 'range_km' => 80]]))
            ->assertCreated()->assertJsonPath('data.details.type', 'scooter');
        $this->assertDatabaseCount('car_details', 0);
        $this->assertDatabaseCount('part_details', 1);
        $this->assertDatabaseCount('moto_details', 1);
    }

    public function test_bicycles_and_incompatible_details_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/listings', $this->car(['section' => 'moto', 'details' => ['type' => 'bicycle', 'brand' => 'TestBrand', 'model' => 'Bike', 'year' => 2025]]))
            ->assertUnprocessable()->assertJsonValidationErrors('details.type');
        $this->postJson('/api/v1/me/listings', $this->car(['details' => ['brand' => 'TestBrand', 'model' => 'Model', 'year' => 2024, 'part_number' => 'P-001']]))
            ->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->postJson('/api/v1/me/listings', $this->car(['section' => ['cars']]))->assertUnprocessable()->assertJsonValidationErrors('section');
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_rental_period_is_required_and_only_cars_can_be_rented(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/listings', $this->car(['offer_type' => 'rent']))->assertUnprocessable()->assertJsonValidationErrors('rental_period');
        $this->postJson('/api/v1/me/listings', $this->car(['section' => 'parts', 'offer_type' => 'rent', 'rental_period' => 'day', 'details' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('offer_type');
    }

    public function test_sale_accepts_a_null_rental_period_and_parts_can_have_empty_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/listings', $this->car(['rental_period' => null]))->assertCreated();
        $this->postJson('/api/v1/me/listings', $this->car(['section' => 'parts', 'details' => []]))
            ->assertCreated()->assertJsonPath('data.section', 'parts');
    }

    public function test_only_published_ads_are_public_and_private_seller_fields_are_hidden(): void
    {
        $public = Listing::factory()->published()->create();
        $private = Listing::factory()->create(['status' => ListingStatus::Pending]);
        $this->getJson('/api/v1/listings')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id)->assertJsonMissingPath('data.0.seller.email')
            ->assertJsonMissingPath('data.0.moderation_reason');
        $this->getJson('/api/v1/listings/'.$private->id)->assertNotFound();
    }

    public function test_filters_pagination_and_decimal_price_sorting_work(): void
    {
        $cheap = Listing::factory()->published()->create(['price' => '9000', 'currency' => 'USD']);
        Listing::factory()->published()->create(['price' => '12000', 'currency' => 'USD']);
        Listing::factory()->published()->create(['price' => '100000000', 'currency' => 'UZS']);
        Listing::factory()->published()->create(['city' => 'Toshkent', 'price' => '1000', 'currency' => 'USD']);
        $this->getJson('/api/v1/listings?section=cars&city=Buxoro&currency=USD&price_max=11000&sort=price_asc&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cheap->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/listings?price_min=10')->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->getJson('/api/v1/listings?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_vehicle_filters_do_not_match_parts_or_other_moto_types(): void
    {
        Listing::factory()->published()->create();
        $scooter = Listing::factory()->published()->create(['section' => MarketplaceSection::Moto]);
        $scooter->motoDetails()->update(['type' => 'scooter']);
        Listing::factory()->published()->create(['section' => MarketplaceSection::Parts]);
        $this->getJson('/api/v1/listings?brand=TestBrand&year_min=2023')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/listings?moto_type=scooter')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $scooter->id);
    }

    public function test_seller_can_only_attach_their_own_active_business_of_the_right_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dealer = Business::factory()->for($user)->create();
        $store = Business::factory()->for($user)->create(['type' => BusinessType::PartsStore]);
        $foreign = Business::factory()->create();
        $this->postJson('/api/v1/me/listings', $this->car(['business_id' => $dealer->id]))->assertCreated();
        foreach ([$store->id, $foreign->id] as $id) {
            $this->postJson('/api/v1/me/listings', $this->car(['business_id' => $id]))->assertUnprocessable()->assertJsonValidationErrors('business_id');
        }
        $dealer->forceFill(['status' => 'pending'])->save();
        $this->postJson('/api/v1/me/listings', $this->car(['business_id' => $dealer->id]))->assertUnprocessable();
    }

    public function test_editing_published_ads_requires_moderation_again(): void
    {
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs($listing->user);
        $this->putJson('/api/v1/me/listings/'.$listing->id, $this->car())
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.published_at', null);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $this->putJson('/api/v1/me/listings/'.$listing->id, $this->car(['section' => 'parts', 'details' => ['part_number' => 'P1']]))
            ->assertUnprocessable()->assertJsonValidationErrors('section');
    }

    public function test_image_upload_is_validated_stored_and_requires_review_after_publication(): void
    {
        Storage::fake('public');
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs($listing->user);
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/images', ['images' => [UploadedFile::fake()->image('car.jpg')]])
            ->assertCreated()->assertJsonCount(1, 'data.images')->assertJsonPath('data.status', 'pending');
        Storage::disk('public')->assertExists($listing->images()->first()->path);
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/images', ['images' => [UploadedFile::fake()->create('script.php', 1, 'text/plain')]])
            ->assertUnprocessable();
    }

    public function test_image_limit_applies_across_multiple_upload_requests(): void
    {
        Storage::fake('public');
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->user);
        for ($index = 0; $index < 10; $index++) {
            $listing->images()->create(['path' => 'existing/'.$index.'.jpg', 'sort_order' => $index]);
        }
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/images', ['images' => [UploadedFile::fake()->image('extra.jpg')]])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseCount('listing_images', 10);
    }
}
