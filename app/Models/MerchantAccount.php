<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantAccount extends Model
{
    protected $fillable = [
        'account_name', 'mid_number', 'processor_name', 'gateway_name',
        'descriptor', 'business_name', 'account_status', 'currency',
        'timezone', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function statementUploads() { return $this->hasMany(MerchantStatementUpload::class); }
    public function statementSummaries() { return $this->hasMany(MerchantStatementSummary::class); }
    public function transactions() { return $this->hasMany(MerchantTransaction::class); }
    public function chargebacks() { return $this->hasMany(MerchantChargeback::class); }
    public function financialEntries() { return $this->hasMany(MerchantFinancialEntry::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeByProcessor($query, string $processor) { return $query->where('processor_name', $processor); }
}
