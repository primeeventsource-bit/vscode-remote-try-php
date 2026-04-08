<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransaction extends Model
{
    protected $fillable = [
        'merchant_account_id', 'statement_upload_id', 'external_transaction_id',
        'order_reference', 'customer_name', 'card_brand', 'last4', 'descriptor',
        'amount', 'currency', 'transaction_status', 'payment_status',
        'refund_status', 'transaction_type', 'transaction_date',
        'source_type', 'source_batch_id', 'raw_data_json',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'raw_data_json' => 'array',
    ];

    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
    public function statementUpload() { return $this->belongsTo(MerchantStatementUpload::class, 'statement_upload_id'); }
    public function chargebacks() { return $this->hasMany(MerchantChargeback::class); }
    public function events() { return $this->hasMany(MerchantTransactionEvent::class); }

    public function scopeApproved($query) { return $query->where('transaction_status', 'approved'); }
    public function scopeDeclined($query) { return $query->where('transaction_status', 'declined'); }
    public function scopeSettled($query) { return $query->where('transaction_status', 'settled'); }
    public function scopeRefunded($query) { return $query->whereIn('transaction_status', ['refunded', 'partial_refund']); }
    public function scopeByMid($query, int $midId) { return $query->where('merchant_account_id', $midId); }
    public function scopeInRange($query, $from = null, $to = null)
    {
        if ($from) $query->where('transaction_date', '>=', $from);
        if ($to) $query->where('transaction_date', '<=', $to);
        return $query;
    }
}
