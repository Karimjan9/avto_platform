<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceProfilesTest extends TestCase
{
    use RefreshDatabase;

    private function car(array $changes = []): array
    {
        return array_replace([
            'section' => 'cars', 'title' => 'Transport sotiladi', 'description' => 'Texnik holati va barcha ma’lumotlari tekshirilgan.',
            'condition' => 'used', 'offer_type' => 'sale', 'price' => '15000.25', 'currency' => 'USD',
            'city' => 'Buxoro', 'contact_phone' => '+998901234567', 'deal_terms' => ['cash', 'installment', 'exchange'],
            'details' => ['brand' => 'Chevrolet', 'model' => 'Cobalt', 'year' => 2024, 'fuel_type' => 'gasoline', 'engine_cc' => 1500, 'gas_equipment' => 'cng', 'generation' => 'II', 'trim' => 'LTZ', 'technical_condition' => 'good'],
        ], $changes);
    }

    private function electric(): array
    {
        return $this->car(['details' => ['brand' => 'BYD', 'model' => 'Yuan', 'year' => 2025, 'fuel_type' => 'electric', 'power_kw' => 150, 'battery_capacity_kwh' => 60.48, 'range_km' => 420, 'charging_types' => ['type2', 'ccs2'], 'battery_health_percent' => 95.5]]);
    }

    private function rental(): array
    {
        return $this->car(['offer_type' => 'rent', 'rental_period' => 'day', 'price' => '30.25', 'rental' => ['monthly_price' => '700', 'deposit' => '0', 'min_days' => 2, 'daily_mileage_limit_km' => null, 'driver_option' => 'without_driver']]);
    }

    private function tyre(): array
    {
        return $this->car(['section' => 'parts', 'details' => ['category' => 'tyres', 'manufacturer' => 'Test', 'part_number' => 'TYRE-01', 'oem_number' => 'OEM-01', 'origin' => 'aftermarket', 'unit' => 'set', 'quantity_per_set' => 4, 'stock_quantity' => 0, 'tyre_width_mm' => 205, 'tyre_aspect_ratio' => 55, 'tyre_diameter_inch' => 16, 'tyre_season' => 'winter', 'manufactured_at' => '2025-03-01']]);
    }

    private function headlight(): array
    {
        return $this->car(['section' => 'parts', 'details' => ['category' => 'headlights', 'side' => 'left', 'position' => 'front', 'origin' => 'original', 'stock_quantity' => 3, 'compatible_vehicles' => [['brand' => 'Chevrolet', 'model' => 'Cobalt', 'generation' => 'II', 'year_from' => 2020, 'year_to' => 2025, 'engine' => '1.5']]]]);
    }

    private function scooter(): array
    {
        return $this->car(['section' => 'moto', 'details' => ['type' => 'scooter', 'brand' => 'Test', 'model' => 'Electric', 'year' => 2025, 'fuel_type' => 'electric', 'power_kw' => 3.5, 'battery_voltage_v' => 72, 'battery_capacity_ah' => 30, 'range_km' => 80, 'transmission' => 'automatic', 'abs' => false]]);
    }

    public function test_form_metadata_changes_with_fuel_category_and_rental_selection(): void
    {
        $ev = $this->getJson('/api/v1/catalog/form?section=cars&fuel_type=electric&condition=used')->assertOk()->json('data');
        $this->assertTrue($ev['fields']['details.battery_capacity_kwh']['required']);
        $this->assertTrue($ev['fields']['details.battery_health_percent']['required']);
        $this->assertSame(['type1', 'type2', 'ccs1', 'ccs2', 'gbt_ac', 'gbt_dc', 'chademo', 'nacs'], array_keys($ev['fields']['details.charging_types.*']['options']));
        $this->assertArrayNotHasKey('details.engine_cc', $ev['fields']);
        $this->assertContains('details.engine_cc', $ev['forbidden_fields']);
        $petrol = $this->getJson('/api/v1/catalog/form?section=cars&fuel_type=gasoline')->assertOk()->json('data.fields');
        $this->assertTrue($petrol['details.engine_cc']['required']);
        $this->assertArrayHasKey('details.gas_equipment', $petrol);
        $this->assertArrayNotHasKey('details.battery_capacity_kwh', $petrol);
        $new = $this->getJson('/api/v1/catalog/form?section=cars&fuel_type=electric&condition=new')->assertOk()->json('data.fields');
        $this->assertFalse($new['details.battery_health_percent']['required']);
        $rent = $this->getJson('/api/v1/catalog/form?section=cars&offer_type=rent&rental_period=month')->assertOk()->json('data.fields');
        $this->assertTrue($rent['rental.deposit']['required']);
        $this->assertSame('price', $rent['rental.monthly_price']['value_from']);
        $tyre = $this->getJson('/api/v1/catalog/form?section=parts&part_category=tyres&part_unit=set')->assertOk()->json('data.fields');
        $this->assertTrue($tyre['details.tyre_width_mm']['required']);
        $this->assertTrue($tyre['details.quantity_per_set']['required']);
        $this->assertArrayNotHasKey('details.side', $tyre);
        $headlight = $this->getJson('/api/v1/catalog/form?section=parts&part_category=headlights')->assertOk()->json('data.fields');
        $this->assertTrue($headlight['details.compatible_vehicles']['required']);
        $this->assertTrue($headlight['details.side']['required']);
        $moto = $this->getJson('/api/v1/catalog/form?section=moto&moto_type=scooter&fuel_type=electric')->assertOk()->json('data.fields');
        $this->assertTrue($moto['details.battery_voltage_v']['required']);
        $this->assertArrayNotHasKey('bicycle', $moto['details.type']['options']);
    }

    public function test_invalid_form_selectors_return_validation_errors(): void
    {
        $this->getJson('/api/v1/catalog/form')->assertUnprocessable()->assertJsonValidationErrors('section');
        $this->getJson('/api/v1/catalog/form?section=moto&moto_type=bicycle')->assertUnprocessable()->assertJsonValidationErrors('moto_type');
        $this->getJson('/api/v1/catalog/form?section=parts&offer_type=rent')->assertUnprocessable()->assertJsonValidationErrors('offer_type');
        $this->getJson('/api/v1/catalog/form?section[]=cars')->assertUnprocessable()->assertJsonValidationErrors('section');
    }

    public function test_all_six_profiles_save_and_their_advertised_required_fields_are_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $profiles = [
            [$this->car(), ['details.engine_cc']],
            [$this->electric(), ['details.battery_capacity_kwh', 'details.range_km', 'details.charging_types', 'details.battery_health_percent']],
            [$this->rental(), ['rental.deposit', 'rental.min_days', 'rental.driver_option']],
            [$this->tyre(), ['details.tyre_width_mm', 'details.tyre_aspect_ratio', 'details.tyre_diameter_inch', 'details.tyre_season', 'details.quantity_per_set']],
            [$this->headlight(), ['details.side', 'details.position', 'details.origin', 'details.compatible_vehicles']],
            [$this->scooter(), ['details.power_kw', 'details.battery_voltage_v', 'details.battery_capacity_ah', 'details.range_km']],
        ];
        foreach ($profiles as [$payload, $required]) {
            $this->postJson('/api/v1/me/listings', $payload)->assertCreated();
            foreach ($required as $key) {
                $missing = $payload;
                Arr::forget($missing, $key);
                $this->postJson('/api/v1/me/listings', $missing)->assertUnprocessable()->assertJsonValidationErrors($key);
            }
        }
        $this->assertDatabaseCount('listings', 6);
        $this->assertDatabaseCount('rental_details', 1);
    }

    public function test_profile_incompatible_fields_and_nested_unknown_keys_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        foreach ([
            [$this->electric(), 'details.engine_cc', 1500],
            [$this->car(), 'details.battery_capacity_kwh', 60],
            [$this->car(), 'rental', ['deposit' => 0]],
            [$this->tyre(), 'details.side', 'left'],
            [$this->headlight(), 'details.tyre_width_mm', 205],
            [$this->scooter(), 'details.engine_cc', 125],
            [$this->headlight(), 'details.compatible_vehicles.0.admin', true],
        ] as [$payload, $key, $value]) {
            Arr::set($payload, $key, $value);
            $this->postJson('/api/v1/me/listings', $payload)->assertUnprocessable();
        }
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_zero_stock_zero_deposit_false_abs_and_decimal_values_survive_round_trip(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/listings', $this->tyre())->assertCreated()
            ->assertJsonPath('data.details.stock_quantity', 0)->assertJsonPath('data.details.quantity_per_set', 4)
            ->assertJsonPath('data.details.manufactured_at', '2025-03-01');
        $this->postJson('/api/v1/me/listings', $this->rental())->assertCreated()
            ->assertJsonPath('data.rental.deposit', '0.00')->assertJsonPath('data.rental.daily_price', '30.25')
            ->assertJsonPath('data.rental.monthly_price', '700.00')->assertJsonPath('data.rental.daily_mileage_limit_km', null);
        $this->postJson('/api/v1/me/listings', $this->scooter())->assertCreated()->assertJsonPath('data.details.abs', false)
            ->assertJsonPath('data.details.power_kw', 3.5);
    }

    public function test_invalid_stock_dates_battery_values_and_duplicate_terms_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        foreach ([
            [$this->tyre(), 'details.stock_quantity', -1],
            [$this->tyre(), 'details.manufactured_at', now()->addDay()->toDateString()],
            [$this->tyre(), 'details.quantity_per_set', 1],
            [$this->electric(), 'details.battery_health_percent', 101],
            [$this->electric(), 'details.charging_types', ['ccs2', 'ccs2']],
            [$this->scooter(), 'details.battery_voltage_v', 0],
            [$this->rental(), 'rental.min_days', 0],
            [$this->rental(), 'rental.daily_price', '31'],
            [$this->headlight(), 'details.compatible_vehicles.0.year_to', 2019],
            [$this->car(), 'deal_terms', ['cash', 'cash']],
            [$this->car(), 'deal_terms', ['unknown']],
            [$this->car(), 'deal_terms', null],
            [$this->car(), 'is_negotiable', null],
        ] as [$payload, $key, $value]) {
            Arr::set($payload, $key, $value);
            $this->postJson('/api/v1/me/listings', $payload)->assertUnprocessable()->assertJsonValidationErrors(str_contains($key, 'charging_types') ? 'details.charging_types.0' : ($key === 'deal_terms' && is_array($value) ? 'deal_terms.0' : $key));
        }
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_switching_powertrain_or_offer_clears_old_profile_data(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/me/listings', $this->electric())->assertCreated()->json('data.id');
        $this->putJson('/api/v1/me/listings/'.$id, $this->car())->assertOk()
            ->assertJsonPath('data.details.battery_capacity_kwh', null)->assertJsonPath('data.details.charging_types', null)
            ->assertJsonPath('data.details.engine_cc', 1500);
        $this->putJson('/api/v1/me/listings/'.$id, $this->rental())->assertOk()->assertJsonPath('data.rental.daily_price', '30.25');
        $this->putJson('/api/v1/me/listings/'.$id, $this->car())->assertOk()->assertJsonPath('data.rental', null)->assertJsonPath('data.rental_period', null);
        $this->assertDatabaseCount('rental_details', 0);
        $part = $this->postJson('/api/v1/me/listings', $this->tyre())->assertCreated()->json('data.id');
        $this->putJson('/api/v1/me/listings/'.$part, $this->headlight() + ['expected_stock_version' => 1])->assertOk()->assertJsonPath('data.details.tyre_width_mm', null)
            ->assertJsonPath('data.details.side', 'left');
    }

    public function test_moto_dealership_and_used_dealership_rental_are_independent_dimensions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/businesses', ['type' => 'moto_dealership', 'name' => 'Moto salon', 'city' => 'Buxoro', 'phone' => '+998901234567'])
            ->assertCreated()->assertJsonPath('data.type', 'moto_dealership')->assertJsonPath('data.status', 'pending');
        $moto = Business::factory()->for($user)->create(['type' => BusinessType::MotoDealership]);
        $car = Business::factory()->for($user)->create();
        $this->postJson('/api/v1/me/listings', $this->scooter() + ['business_id' => $moto->id])->assertCreated()
            ->assertJsonPath('data.seller_type', 'moto_dealership');
        $this->postJson('/api/v1/me/listings', $this->scooter() + ['business_id' => $car->id])->assertUnprocessable()->assertJsonValidationErrors('business_id');
        $this->postJson('/api/v1/me/listings', $this->rental() + ['business_id' => $car->id])->assertCreated()
            ->assertJsonPath('data.condition', 'used')->assertJsonPath('data.offer_type', 'rent')->assertJsonPath('data.seller_type', 'car_dealership');
        $this->postJson('/api/v1/me/listings', $this->car())->assertCreated()->assertJsonPath('data.seller_type', 'private')
            ->assertJsonPath('data.deal_terms', ['cash', 'installment', 'exchange']);
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('data.business_types.moto_dealership.section', 'moto');
    }

    public function test_combined_seller_terms_and_technical_filters_select_the_correct_listing(): void
    {
        $dealer = Business::factory()->create();
        $car = Listing::factory()->published()->create(['user_id' => $dealer->user_id, 'business_id' => $dealer->id, 'condition' => 'used', 'offer_type' => 'rent', 'rental_period' => 'day', 'deal_terms' => ['cash', 'installment']]);
        $car->carDetails()->update(['generation' => 'II', 'fuel_type' => 'gasoline', 'transmission' => 'automatic']);
        $car->rentalDetails()->create(['daily_price' => $car->price, 'deposit' => 0, 'min_days' => 1, 'driver_option' => 'without_driver']);
        Listing::factory()->published()->create();
        $this->getJson('/api/v1/listings?section=cars&condition=used&offer_type=rent&seller_type=car_dealership&deal_term=installment&generation=II&fuel_type=gasoline&transmission=automatic&driver_option=without_driver')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $car->id);
        $this->getJson('/api/v1/listings?seller_type=private')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/listings?deal_term=exchange')->assertOk()->assertJsonCount(0, 'data');
        $tyre = Listing::factory()->published()->create(['section' => 'parts']);
        $tyre->partDetails()->update($this->tyre()['details'] + ['compatible_brands' => null]);
        $tyre->partDetails()->update(['stock_quantity' => 4]);
        $this->getJson('/api/v1/listings?part_category=tyres&in_stock=1&tyre_width_mm=205&tyre_season=winter&oem_number=OEM-01&part_origin=aftermarket')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tyre->id);
        $moto = Listing::factory()->published()->create(['section' => 'moto']);
        $moto->motoDetails()->update($this->scooter()['details']);
        $this->getJson('/api/v1/listings?section=moto&fuel_type=electric&abs=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $moto->id);
    }
}
