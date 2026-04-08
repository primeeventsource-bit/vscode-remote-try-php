<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealFinancial extends Model
{
    protected $table = 'deal_financials';

    protected $fillable = [
        'deal_id',
        'fronter_percent', 'closer_percent', 'admin_percent',
        'processing_percent', 'reserve_percent', 'marketing_percent',
        'gross_amount', 'collected_amount', 'refunded_amount', 'chargeback_amount',
        'fronter_commission', 'closer_commission', 'admin_commission',
        'processing_fee', 'reserve_fee', 'marketing_cost',
        'company_net', 'company_net_percent',
        'manual_adjustment', 'adjustment_reason',
        'is_locked', 'is_disputed', 'is_reversed',
        'calculated_at', 'locked_at', 'approved_at',
        'created_by', 'approved_by', 'updated_by',
    ];

    protected $casts = [
        'fronter_percent' => 'decimal:2',
        'closer_percent' => 'decimal:2',
        'admin_percent' => 'decimal:2',
        'processing_percent' => 'decimal:2',
        'reserve_percent' => 'decimal:2',
        'marketing_percent' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'chargeback_amount' => 'decimal:2',
        'fronter_commission' => 'decimal:2',
        'closer_commission' => 'decimal:2',
        'admin_commission' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'reserve_fee' => 'decimal:2',
        'marketing_cost' => 'decimal:2',
        'company_net' => 'decimal:2',
        'company_net_percent' => 'decimal:4',
        'manual_adjustment' => 'decimal:2',
        'is_locked' => 'boolean',
        'is_disputed' => 'boolean',
        'is_reversed' => 'boolean',
        'calculated_at' => 'datetime',
        'locked_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function deal() { return $this->belongsTo(Deal::class); }
    public function createdByUser() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedByUser() { return $this->belongsTo(User::class, 'approved_by'); }

    public function getCalculationBaseAttribute(): float
    {
        $base = (float) $this->collected_amount > 0 ? (float) $this->collected_amount : (float) $this->gross_amount;
        $base -= (float) $this->refunded_amount;
        return max($base, 0);
    }

    public function getTotalCommissionsAttribute(): float
    {
        return (float) $this->fronter_commission + (float) $this->closer_commission + (float) $this->admin_commission;
    }

    public function getTotalDeductionsAttribute(): float
    {
        return $this->total_commissions + (float) $this->processing_fee + (float) $this->reserve_fee + (float) $this->marketing_cost;
    }
}
