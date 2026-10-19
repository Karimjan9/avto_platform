<?php

namespace App\Http\Requests;

use App\Enums\BusinessType;
use App\Enums\MarketplaceSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['sometimes', Rule::enum(MarketplaceSection::class)],
            'condition' => ['sometimes', Rule::in(array_keys(config('marketplace.conditions')))],
            'offer_type' => ['sometimes', Rule::in(array_keys(config('marketplace.offer_types')))],
            'business_id' => ['sometimes', 'integer', 'min:1'],
            'business_branch_id' => ['sometimes', 'integer', 'min:1'],
            'vehicle_variant_id' => ['sometimes', 'integer', 'min:1'],
            'part_product_id' => ['sometimes', 'integer', 'min:1'],
            'seller_type' => ['sometimes', Rule::in(['private', ...array_column(BusinessType::cases(), 'value')])],
            'deal_term' => ['sometimes', Rule::in(array_keys(config('marketplace.deal_terms')))],
            'city' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
            'currency' => [Rule::requiredIf($this->hasAny(['price_min', 'price_max']) || in_array($this->input('sort'), ['price_asc', 'price_desc'], true)), Rule::in(config('marketplace.currencies'))],
            'price_min' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999'],
            'price_max' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999', ...($this->has('price_min') ? ['gte:price_min'] : [])],
            'brand' => ['sometimes', 'string', 'max:80'],
            'model' => ['sometimes', 'string', 'max:80'],
            'year_min' => ['sometimes', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'year_max' => ['sometimes', 'integer', 'min:1900', 'max:'.(now()->year + 1), ...($this->has('year_min') ? ['gte:year_min'] : [])],
            'moto_type' => ['sometimes', Rule::in(array_keys(config('marketplace.moto_types')))],
            'fuel_type' => ['sometimes', Rule::in(config('marketplace.fuel_types'))],
            'transmission' => ['sometimes', Rule::in(array_unique([...config('marketplace.transmissions'), ...config('marketplace.moto_transmissions')]))],
            'generation' => ['sometimes', 'string', 'max:80'],
            'trim' => ['sometimes', 'string', 'max:120'],
            'body_type' => ['sometimes', Rule::in(config('marketplace.body_types'))],
            'technical_condition' => ['sometimes', Rule::in(array_keys(config('marketplace.technical_conditions')))],
            'abs' => ['sometimes', 'boolean'],
            'part_category' => ['sometimes', Rule::in(array_keys(config('marketplace.part_categories')))],
            'manufacturer' => ['sometimes', 'string', 'max:120'],
            'oem_number' => ['sometimes', 'string', 'max:80'],
            'part_number' => ['sometimes', 'string', 'max:80'],
            'part_origin' => ['sometimes', Rule::in(array_keys(config('marketplace.part_origins')))],
            'in_stock' => ['sometimes', 'boolean'],
            'tyre_width_mm' => ['sometimes', 'integer', 'min:100', 'max:500'],
            'tyre_aspect_ratio' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'tyre_diameter_inch' => ['sometimes', 'integer', 'min:8', 'max:30'],
            'tyre_season' => ['sometimes', Rule::in(array_keys(config('marketplace.tyre_seasons')))],
            'driver_option' => ['sometimes', Rule::in(array_keys(config('marketplace.driver_options')))],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'price_asc', 'price_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
