<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantFinancialEntry extends Model
{
    protected $fillable = [
        'merchant_account_id', 'statement_upload_id', 'entry_type',
        'category', 'description', 'amount', 'currency', 'entry_date',
        'external_reference', 'raw_data_json',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
        'raw_data_json' => 'array',
    ];

    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
    public function statementUpload() { return $this->belongsTo(MerchantStatementUpload::class, 'statement_upload_id'); }

    public function scopeFees($query) { return $query->where('entry_type', 'fee'); }
    public function scopeReserveHolds($query) { return $query->where('entry_type', 'reserve_hold'); }
    public function scopeReserveReleases($query) { return $query->where('entry_type', 'reserve_release'); }
    public function scopePayouts($query) { return $query->whereIn('entry_type', ['payout', 'deposit']); }
    public function scopeAdjustments($query) { return $query->where('entry_type', 'adjustment'); }
    public function scopeByMid($query, int $midId) { return $query->where('merchant_account_id', $midId); }
    public function scopeInRange($query, $from = null, $to = null)
    {
        if ($from) $query->where('entry_date', '>=', $from);
        if ($to) $query->where('entry_date', '<=', $to);
        return $query;
    }
}
