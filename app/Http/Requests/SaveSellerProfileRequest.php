<?php

namespace App\Http\Requests;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedPhone() ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'city', 'business_name', 'address', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $shop = $this->input('seller_type') === 'shop';

        return [
            'seller_type' => ['required', Rule::in(['individual', 'shop'])],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'business_name' => [Rule::requiredIf($shop), 'nullable', 'string', 'min:2', 'max:160'],
            'business_type' => [Rule::requiredIf($shop), 'nullable', Rule::enum(BusinessType::class)],
            'sections' => [Rule::requiredIf($shop), 'nullable', 'array', 'list', 'min:1', 'max:3'],
            'sections.*' => ['required', Rule::in(['cars', 'parts', 'moto']), 'distinct'],
            'address' => [Rule::requiredIf($shop), 'nullable', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['prohibited'], 'contact_phone' => ['prohibited'],
            'phone_verified_at' => ['prohibited'], 'seller_onboarded_at' => ['prohibited'],
            'business_id' => ['prohibited'], 'user_id' => ['prohibited'],
            'status' => ['prohibited'], 'is_admin' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $type = is_string($this->input('business_type')) ? BusinessType::tryFrom($this->input('business_type')) : null;
            if ($this->input('seller_type') === 'shop' && $type && is_array($this->input('sections')) && ! in_array($type->section()->value, $this->input('sections'), true)) {
                $validator->errors()->add('sections', 'Asosiy biznes yo‘nalishini ham belgilang.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['name' => 'Ism', 'city' => 'Shahar', 'business_name' => 'Do‘kon nomi', 'business_type' => 'Biznes turi', 'sections' => 'Yo‘nalishlar', 'address' => 'Manzil', 'description' => 'Tavsif'];
    }
}
