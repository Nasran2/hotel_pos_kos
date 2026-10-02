<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestKitchenStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.send_kitchen') ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
