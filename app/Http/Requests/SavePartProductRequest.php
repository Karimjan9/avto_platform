<?php

namespace App\Http\Requests;

use App\Models\PartProduct;
use App\Models\VehicleVariant;
use App\Services\ListingFormSchema;
use App\Services\VehicleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePartProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(ListingFormSchema $schema): array
    {
        $rules = [
            'title' => ['required', 'string', 'between:3,160'], 'manufacturer' => ['required', 'string', 'max:120'],
            'part_number' => ['required', 'string', 'max:80', 'regex:/[\pL\pN]/u'], 'oem_number' => ['nullable', 'string', 'max:80'],
            'category' => ['required', Rule::in(array_keys(config('marketplace.part_categories')))],
            'attributes' => ['present', 'array:'.implode(',', PartProduct::TECHNICAL_FIELDS)],
            'fitments' => [$this->input('category') === 'headlights' ? 'required' : 'sometimes', 'array', 'list', 'max:50'],
            'fitments.*' => ['array:vehicle_variant_id,year_from,year_to'],
            'fitments.*.vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id', 'distinct'],
            'fitments.*.year_from' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'fitments.*.year_to' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1), 'gte:fitments.*.year_from'],
            'status' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
        $profile = $schema->rules(['section' => 'parts', 'details' => ['category' => $this->input('category')]]);
        foreach (PartProduct::TECHNICAL_FIELDS as $field) {
            $rules['attributes.'.$field] = $profile['details.'.$field];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('fitments', []) as $index => $fitment) {
                $variant = VehicleVariant::find($fitment['vehicle_variant_id']);
                foreach (['year_from', 'year_to'] as $key) {
                    if (isset($fitment[$key]) && ! app(VehicleCatalog::class)->validYear($variant, $fitment[$key])) {
                        $validator->errors()->add('fitments.'.$index.'.'.$key, 'Moslik yili katalog avlodidan tashqarida.');
                    }
                }
            }
        }];
    }
}
