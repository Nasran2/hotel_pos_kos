<?php

namespace App\Http\Requests;

use App\Services\KitchenDisplayAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenDisplayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(KitchenDisplayAccess::class)->isUnlocked($this);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:preparing,ready,stopped'],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
