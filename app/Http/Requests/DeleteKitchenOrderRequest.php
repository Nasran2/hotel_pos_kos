<?php

namespace App\Http\Requests;

use App\Services\KitchenDisplayAccess;
use Illuminate\Foundation\Http\FormRequest;

class DeleteKitchenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('kod.*')
            ? app(KitchenDisplayAccess::class)->isUnlocked($this)
            : ($this->user()?->can('kitchen.update') ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1']];
    }
}
