<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementDeposit extends Model
{
    use HasFactory;

    protected $table = 'statement_deposits';

    protected $fillable = [
        'statement_id',
        'deposit_day',
        'deposit_date',
        'reference_number',
        'batch_id',
        'tran_code',
        'plan_code',
        'sales_count',
        'sales_amount',
        'credits_amount',
        'discount_paid',
        'net_deposit',
        'raw_row_text',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'sales_amount' => 'decimal:2',
        'credits_amount' => 'decimal:2',
        'discount_paid' => 'decimal:2',
        'net_deposit' => 'decimal:2',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }
}
