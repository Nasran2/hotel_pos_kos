<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTokenCounter extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'token_date',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_date' => 'date',
            'last_number' => 'integer',
        ];
    }
}
