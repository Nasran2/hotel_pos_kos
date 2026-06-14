<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_category_id',
        'user_id',
        'amount',
        'payment_method',
        'bank_account_id',
        'expense_date',
        'note',
        'attachment_path',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'expense_date' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
