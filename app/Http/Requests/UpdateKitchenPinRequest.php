<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/\A[0-9]{4}\z/', 'confirmed']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pin.regex' => 'The kitchen PIN must contain exactly four digits.'];
    }
}
