<?php

namespace App\Http\Requests;

use App\Enums\BusinessType;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $this->user() && (! $business instanceof Business || $this->user()->can('update', $business));
    }

    public function rules(): array
    {
        $business = $this->route('business');

        return [
            'type' => ['required', Rule::enum(BusinessType::class), ...($business instanceof Business ? [Rule::in([$business->type->value])] : [])],
            'sections' => ['sometimes', 'array', 'list', 'min:1', 'max:3'],
            'sections.*' => ['required', Rule::in(['cars', 'parts', 'moto']), 'distinct'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'status' => ['prohibited'], 'user_id' => ['prohibited'], 'slug' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $type = is_string($this->input('type')) ? BusinessType::tryFrom($this->input('type')) : null;
            if ($type && is_array($this->input('sections')) && ! in_array($type->section()->value, $this->input('sections'), true)) {
                $validator->errors()->add('sections', 'Asosiy yo‘nalish ro‘yxatda qolishi kerak.');
            }
        }];
    }
}
