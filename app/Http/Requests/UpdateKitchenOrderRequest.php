<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('pos.kitchen.served') ? 'pos.send_kitchen' : 'kitchen.update');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', $this->routeIs('pos.kitchen.served') ? 'in:served' : 'in:preparing,ready,cancelled,stopped'],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
