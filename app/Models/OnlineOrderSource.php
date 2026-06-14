<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'commission_type', 'commission_value', 'default_payment_method', 'is_active', 'notes'])]
class OnlineOrderSource extends Model
{
    protected function casts(): array
    {
        return [
            'commission_value' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OnlineOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class);
    }
}
