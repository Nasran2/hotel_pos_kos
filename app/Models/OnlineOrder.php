<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'register_id',
    'online_order_source_id',
    'sale_id',
    'order_token_id',
    'order_reference',
    'customer_id',
    'customer_name',
    'customer_phone',
    'delivery_address',
    'subtotal',
    'discount_type',
    'discount_value',
    'discount_amount',
    'delivery_charge',
    'commission_type',
    'commission_value',
    'commission_amount',
    'total',
    'paid_amount',
    'balance_amount',
    'payment_status',
    'payment_method',
    'order_status',
    'stock_reduced_at',
    'notes',
    'created_by',
])]
class OnlineOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'discount_value' => 'float',
            'discount_amount' => 'float',
            'delivery_charge' => 'float',
            'commission_value' => 'float',
            'commission_amount' => 'float',
            'total' => 'float',
            'paid_amount' => 'float',
            'balance_amount' => 'float',
            'stock_reduced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OnlineOrderSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(OnlineOrderSource::class, 'online_order_source_id');
    }

    /**
     * @return BelongsTo<OrderToken, $this>
     */
    public function orderToken(): BelongsTo
    {
        return $this->belongsTo(OrderToken::class);
    }

    /**
     * @return HasMany<OnlineOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class);
    }

    /**
     * @return HasMany<OnlineOrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OnlineOrderPayment::class);
    }
}
