<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockKitchenDisplayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/\A[0-9]{4}\z/']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pin.regex' => 'Enter exactly four digits.'];
    }
}
