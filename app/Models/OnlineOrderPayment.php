<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['online_order_id', 'register_id', 'amount', 'payment_method', 'payment_date', 'note', 'created_by'])]
class OnlineOrderPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payment_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OnlineOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
    }
}
