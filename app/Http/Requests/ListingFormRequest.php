<?php

namespace App\Http\Requests;

use App\Enums\MarketplaceSection;
use App\Models\PartProduct;
use App\Models\VehicleVariant;
use App\Services\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', Rule::enum(MarketplaceSection::class), Rule::exists('marketplace_sections', 'code')->where('is_active', true)],
            'condition' => ['sometimes', Rule::in(array_keys(config('marketplace.conditions')))],
            'offer_type' => ['sometimes', Rule::in($this->input('section') === 'cars' ? ['sale', 'rent'] : ['sale'])],
            'rental_period' => ['sometimes', Rule::in(array_keys(config('marketplace.rental_periods')))],
            'fuel_type' => ['sometimes', Rule::in($this->input('section') === 'moto' ? ['gasoline', 'electric'] : config('marketplace.fuel_types'))],
            'part_category' => ['sometimes', Rule::in(array_keys(config('marketplace.part_categories')))],
            'part_unit' => ['sometimes', Rule::in(array_keys(config('marketplace.part_units')))],
            'moto_type' => ['sometimes', Rule::in(array_keys(config('marketplace.moto_types')))],
            'vehicle_variant_id' => ['sometimes', 'integer', 'exists:vehicle_variants,id', Rule::prohibitedIf(! in_array($this->input('section'), ['cars', 'moto'], true))],
            'part_product_id' => ['sometimes', 'integer', Rule::exists('part_products', 'id')->where('status', 'active'), Rule::prohibitedIf($this->input('section') !== 'parts')],
        ];
    }

    public function context(): array
    {
        $data = $this->validated();

        $context = array_filter([
            'section' => $data['section'], 'condition' => $data['condition'] ?? 'used',
            'offer_type' => $data['offer_type'] ?? 'sale', 'rental_period' => $data['rental_period'] ?? null,
            'details' => array_filter(['fuel_type' => $data['fuel_type'] ?? null, 'category' => $data['part_category'] ?? null, 'unit' => $data['part_unit'] ?? null, 'type' => $data['moto_type'] ?? null], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null);
        $context['details'] = array_replace($context['details'], $this->catalogDefaults());
        foreach (['vehicle_variant_id', 'part_product_id'] as $key) {
            if (isset($data[$key])) {
                $context[$key] = $data[$key];
            }
        }

        return $context;
    }

    public function catalogDefaults(): array
    {
        if ($this->filled('vehicle_variant_id')) {
            return app(VehicleCatalog::class)->defaults(VehicleVariant::findOrFail($this->integer('vehicle_variant_id')));
        }
        if ($this->filled('part_product_id')) {
            return PartProduct::where('status', 'active')->findOrFail($this->integer('part_product_id'))->detailDefaults();
        }

        return [];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('vehicle_variant_id')) {
                return;
            }
            $variant = VehicleVariant::findOrFail($this->integer('vehicle_variant_id'));
            if ($variant->generation->model->section !== $this->input('section')) {
                $validator->errors()->add('vehicle_variant_id', 'Katalog varianti bo‘limga mos emas.');
            }
        }];
    }
}
