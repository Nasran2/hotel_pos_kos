<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnlineOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('online_orders.create');
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('items_payload');
        $this->merge(['items' => is_string($payload) ? json_decode($payload, true) : null]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'online_order_source_id' => ['required', Rule::exists('online_order_sources', 'id')->where('is_active', true)],
            'order_reference' => ['required', 'string', 'max:255', 'unique:online_orders,order_reference'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', $this->input('discount_type') === 'percentage' ? 'max:100' : 'max:9999999'],
            'delivery_charge' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
            'payment_status' => ['required', 'in:paid,cash_on_delivery,pending,partially_paid'],
            'payment_method' => ['required', 'in:cash,bank,card,online,platform_payment'],
            'order_status' => ['required', 'in:new'],
            'paid_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999', 'required_if:payment_status,partially_paid', $this->input('payment_status') === 'partially_paid' ? 'gt:0' : 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items_payload' => ['required', 'string', 'max:100000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['required', 'array:id,qty,price'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:99999'],
            'items.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999'],
        ];
    }
}
