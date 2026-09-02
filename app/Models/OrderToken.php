<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderToken extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'token_date',
        'token_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_date' => 'date',
            'token_number' => 'integer',
        ];
    }
}
