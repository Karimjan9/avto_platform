<?php

namespace App\Http\Requests;

use App\Services\PhoneOtpService;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SendPhoneCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            try {
                $this->merge(['phone' => PhoneOtpService::normalizePhone($this->input('phone'))]);
            } catch (InvalidArgumentException) {
                // The ordinary validation error below is safe for malformed input.
            }
        }
    }

    public function rules(): array
    {
        return ['phone' => ['bail', 'required', 'string', 'regex:/^\+998[0-9]{9}$/D']];
    }

    public function messages(): array
    {
        return ['phone.*' => 'Telefon raqamini +998 90 123 45 67 ko‘rinishida kiriting.'];
    }
}
