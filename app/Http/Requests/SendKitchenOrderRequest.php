<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendKitchenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.send_kitchen');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'table_id' => ['nullable', Rule::exists('restaurant_tables', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'hold_id' => ['nullable', Rule::exists('hold_orders', 'id')->whereNull('deleted_at')->whereIn('status', ['hold', 'payment_pending'])],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'waiter_id' => ['nullable', Rule::exists('waiters', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.id' => ['required', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
