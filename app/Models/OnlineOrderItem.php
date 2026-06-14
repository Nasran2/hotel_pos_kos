<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['online_order_id', 'product_id', 'product_name', 'qty', 'unit_price', 'total'])]
class OnlineOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_price' => 'float',
            'total' => 'float',
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
