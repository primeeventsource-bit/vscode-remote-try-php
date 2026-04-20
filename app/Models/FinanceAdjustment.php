<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAdjustment extends Model
{
    use HasFactory;

    protected $table = 'finance_adjustments';

    protected $fillable = [
        'merchant_account_id',
        'adjustment_month',
        'description',
        'amount',
        'type',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
