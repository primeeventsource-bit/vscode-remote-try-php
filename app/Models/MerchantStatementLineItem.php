<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantStatementLineItem extends Model
{
    protected $fillable = [
        'merchant_statement_upload_id', 'merchant_account_id', 'line_type',
        'external_reference', 'transaction_date', 'description', 'amount',
        'currency', 'mapped_status', 'raw_line_json', 'confidence_score',
        'needs_review',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'needs_review' => 'boolean',
        'raw_line_json' => 'array',
    ];

    public function upload() { return $this->belongsTo(MerchantStatementUpload::class, 'merchant_statement_upload_id'); }
    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }

    public function scopeReviewNeeded($query) { return $query->where('needs_review', true); }
    public function scopeByType($query, string $type) { return $query->where('line_type', $type); }
}
