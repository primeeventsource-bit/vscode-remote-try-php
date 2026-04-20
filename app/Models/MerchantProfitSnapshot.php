<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantProfitSnapshot extends Model
{
    use HasFactory;

    protected $table = 'merchant_profit_snapshots';

    protected $fillable = [
        'merchant_account_id',
        'snapshot_month',
        'gross_sales',
        'credits',
        'net_sales',
        'net_deposits',
        'discount_fees',
        'other_processor_fees',
        'total_chargebacks',
        'total_reversals',
        'net_chargeback_loss',
        'dispute_fees',
        'payroll_cost',
        'operating_expenses',
        'adjustments',
        'true_net_profit',
        'profit_margin_pct',
        'chargeback_ratio_pct',
        'fee_to_volume_ratio_pct',
        'reserve_balance',
        'mid_health_score',
        'waterfall_json',
    ];

    protected $casts = [
        'gross_sales' => 'decimal:2',
        'credits' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'net_deposits' => 'decimal:2',
        'discount_fees' => 'decimal:2',
        'other_processor_fees' => 'decimal:2',
        'total_chargebacks' => 'decimal:2',
        'total_reversals' => 'decimal:2',
        'net_chargeback_loss' => 'decimal:2',
        'dispute_fees' => 'decimal:2',
        'payroll_cost' => 'decimal:2',
        'operating_expenses' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'true_net_profit' => 'decimal:2',
        'profit_margin_pct' => 'decimal:4',
        'chargeback_ratio_pct' => 'decimal:4',
        'fee_to_volume_ratio_pct' => 'decimal:4',
        'reserve_balance' => 'decimal:2',
        'waterfall_json' => 'array',
    ];

    public function merchantAccount()
    {
        return $this->belongsTo(MerchantAccount::class);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('snapshot_month', $month);
    }
}
