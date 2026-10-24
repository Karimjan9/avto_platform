<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['bail', 'required', 'string', 'regex:/^[0-9]{6}$/D']];
    }

    public function messages(): array
    {
        return ['code.*' => 'SMSdagi 6 xonali kodni kiriting.'];
    }
}
