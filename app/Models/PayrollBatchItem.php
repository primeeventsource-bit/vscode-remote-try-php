<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollBatchItem extends Model
{
    protected $fillable = [
        'payroll_batch_id', 'user_id', 'role_code',
        'gross_volume', 'deal_count', 'base_commission',
        'bonus_amount', 'hold_amount', 'deduction_amount',
        'adjustment_amount', 'final_payout', 'payout_status', 'notes',
    ];

    protected $casts = [
        'gross_volume' => 'decimal:2',
        'base_commission' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'hold_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'final_payout' => 'decimal:2',
    ];

    public function batch() { return $this->belongsTo(PayrollBatchV2::class, 'payroll_batch_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
