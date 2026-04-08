<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantChargeback extends Model
{
    protected $fillable = [
        'merchant_account_id', 'merchant_transaction_id', 'statement_upload_id',
        'external_chargeback_id', 'amount', 'currency', 'card_brand',
        'reason_code', 'reason_description', 'processor_status',
        'internal_status', 'evidence_status', 'opened_at', 'due_at',
        'resolved_at', 'outcome', 'notes', 'raw_data_json',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'opened_at' => 'date',
        'due_at' => 'date',
        'resolved_at' => 'date',
        'raw_data_json' => 'array',
    ];

    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
    public function transaction() { return $this->belongsTo(MerchantTransaction::class, 'merchant_transaction_id'); }
    public function statementUpload() { return $this->belongsTo(MerchantStatementUpload::class, 'statement_upload_id'); }
    public function events() { return $this->hasMany(MerchantChargebackEvent::class); }

    public function scopeOpen($query) { return $query->whereIn('internal_status', ['new', 'open', 'pending_response']); }
    public function scopeDueSoon($query, int $days = 7) { return $query->where('due_at', '<=', now()->addDays($days))->where('due_at', '>=', now()); }
    public function scopeOverdue($query) { return $query->where('due_at', '<', now())->whereNotIn('internal_status', ['won', 'lost', 'reversed', 'closed']); }
    public function scopeWon($query) { return $query->where('outcome', 'won'); }
    public function scopeLost($query) { return $query->where('outcome', 'lost'); }
    public function scopeByMid($query, int $midId) { return $query->where('merchant_account_id', $midId); }
}
