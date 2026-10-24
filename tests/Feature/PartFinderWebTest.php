<?php

namespace Tests\Feature;

use App\Models\PartProduct;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class PartFinderWebTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_finder_matches_active_products_by_model_fitment_year_and_generation_year(): void
    {
        $variant = $this->variant();
        $variant->generation->update(['year_to' => 2025]);
        $matched = $this->fittedProduct($variant, 'Mos tormoz kolodkasi', 2020, 2025);
        $this->offer($this->company(), $matched);
        $draft = $this->offer($this->company(), $matched);
        $draft->forceFill(['status' => 'draft'])->save();
        $expired = $this->fittedProduct($variant, 'Eski yilga mos detal', 2010, 2019);
        $pending = $this->fittedProduct($variant, 'Tekshiruvdagi detal', 2020, 2025);
        $pending->update(['status' => 'pending']);
        $oldGeneration = VehicleGeneration::create(['vehicle_model_id' => $variant->generation->vehicle_model_id, 'name' => 'Oldingi avlod', 'year_from' => 2000, 'year_to' => 2005]);
        $oldVariant = VehicleVariant::create(['vehicle_generation_id' => $oldGeneration->id, 'name' => 'Eski motor', 'fuel_type' => 'gasoline']);
        $wrongGeneration = $this->fittedProduct($oldVariant, 'Oldingi avlod detali');

        $this->get(route('parts.find', $this->selection($variant)))->assertOk()
            ->assertSee($matched->title)->assertSee('1 ta do‘kon taklifi')
            ->assertDontSee($expired->title)->assertDontSee($pending->title)->assertDontSee($wrongGeneration->title)
            ->assertSee(route('listings', ['section' => 'parts', 'part_product_id' => $matched->id]));
        $this->get(route('parts.find', $this->selection($variant, 2026)))->assertOk()
            ->assertDontSee($matched->title)->assertSee('Bu yil uchun katalogda transport varianti topilmadi');
    }

    public function test_exact_variant_selection_excludes_products_for_other_engines(): void
    {
        $first = $this->variant();
        $second = VehicleVariant::create(['vehicle_generation_id' => $first->vehicle_generation_id, 'name' => '2.0 dizel', 'fuel_type' => 'diesel', 'engine_cc' => 2000]);
        $firstProduct = $this->fittedProduct($first, 'Birinchi motor detali');
        $secondProduct = $this->fittedProduct($second, 'Ikkinchi motor detali');
        $selection = $this->selection($first);

        $this->get(route('parts.find', $selection))->assertOk()->assertSee($firstProduct->title)->assertSee($secondProduct->title)
            ->assertSee('Aniqroq moslik uchun o‘zingiznikini tanlang.');
        $this->get(route('parts.find', $selection + ['vehicle_variant_id' => $first->id]))->assertOk()
            ->assertSee($firstProduct->title)->assertDontSee($secondProduct->title);
    }

    public function test_crossed_make_model_variant_and_invalid_year_are_rejected(): void
    {
        $variant = $this->variant();
        $selection = $this->selection($variant);
        $otherMake = VehicleMake::create(['name' => 'Boshqa marka']);
        $otherModel = VehicleModel::create(['vehicle_make_id' => $otherMake->id, 'section' => 'cars', 'name' => 'Boshqa model']);
        $otherGeneration = VehicleGeneration::create(['vehicle_model_id' => $otherModel->id, 'name' => 'I', 'year_from' => 2020]);
        $otherVariant = VehicleVariant::create(['vehicle_generation_id' => $otherGeneration->id, 'name' => 'Boshqa motor', 'fuel_type' => 'gasoline']);
        $moto = $this->variant('moto');

        $this->getJson(route('parts.find', array_replace($selection, ['vehicle_make_id' => $otherMake->id])))->assertUnprocessable()->assertJsonValidationErrors('vehicle_model_id');
        $this->getJson(route('parts.find', array_replace($selection, ['vehicle_model_id' => $moto->generation->vehicle_model_id])))->assertUnprocessable()->assertJsonValidationErrors('vehicle_model_id');
        $this->getJson(route('parts.find', $selection + ['vehicle_variant_id' => $otherVariant->id]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_variant_id');
        $variant->generation->update(['year_to' => 2023]);
        $this->getJson(route('parts.find', $selection + ['vehicle_variant_id' => $variant->id]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_variant_id');
        $this->getJson(route('parts.find', array_replace($selection, ['year' => now()->year + 2])))->assertUnprocessable()->assertJsonValidationErrors('year');
        $this->getJson(route('parts.find'))->assertUnprocessable()->assertJsonValidationErrors(['vehicle_make_id', 'vehicle_model_id', 'year']);
    }

    private function fittedProduct(VehicleVariant $variant, string $title, ?int $from = null, ?int $to = null): PartProduct
    {
        $product = $this->product();
        $product->update(['title' => $title]);
        $product->fitments()->create(['vehicle_variant_id' => $variant->id, 'year_from' => $from, 'year_to' => $to]);

        return $product;
    }

    private function selection(VehicleVariant $variant, int $year = 2024): array
    {
        return ['vehicle_make_id' => $variant->generation->model->vehicle_make_id, 'vehicle_model_id' => $variant->generation->vehicle_model_id, 'year' => $year];
    }
}
