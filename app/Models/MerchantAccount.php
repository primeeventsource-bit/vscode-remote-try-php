<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'processor_id',
        'name',
        'mid_masked',
        'merchant_number',
        'association_number',
        'routing_last4',
        'deposit_account_last4',
        'currency',
        'profit_methodology',
        'notes',
        'active',
        // Finance Command Center columns
        'account_name',
        'mid_number',
        'processor_name',
        'gateway_name',
        'descriptor',
        'business_name',
        'account_status',
        'timezone',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Accessors — fall back to old columns when new columns are empty
    public function getAccountNameAttribute($value)
    {
        return $value ?: ($this->attributes['name'] ?? 'Unknown');
    }

    public function getMidNumberAttribute($value)
    {
        return $value ?: ($this->attributes['mid_masked'] ?? '--');
    }

    public function getProcessorNameAttribute($value)
    {
        if ($value && $value !== 'Unknown') return $value;
        // Fall back to processor relationship
        try {
            return $this->processor?->name ?? $value ?? 'Unknown';
        } catch (\Throwable) {
            return $value ?? 'Unknown';
        }
    }

    public function processor()
    {
        return $this->belongsTo(Processor::class);
    }

    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }

    public function merchantChargebacks()
    {
        return $this->hasMany(MerchantChargeback::class);
    }

    public function statements()
    {
        return $this->hasMany(MerchantStatement::class);
    }

    public function liveChargebacks()
    {
        return $this->hasMany(LiveChargeback::class);
    }

    public function expenses()
    {
        return $this->hasMany(FinanceManualExpense::class);
    }

    public function adjustments()
    {
        return $this->hasMany(FinanceAdjustment::class);
    }

    public function profitSnapshots()
    {
        return $this->hasMany(MerchantProfitSnapshot::class);
    }

    public function latestStatement()
    {
        return $this->hasOne(MerchantStatement::class)->latestOfMany('parsed_at');
    }

    public function statementUploads()
    {
        return $this->hasMany(MerchantStatementUpload::class);
    }

    public function scopeActive($query)
    {
        // Support both old 'active' and new 'is_active' columns
        if (\Illuminate\Support\Facades\Schema::hasColumn('merchant_accounts', 'is_active')) {
            return $query->where('is_active', true);
        }
        return $query->where('active', true);
    }
}
