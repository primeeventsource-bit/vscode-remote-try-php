<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantStatementUpload extends Model
{
    protected $fillable = [
        'merchant_account_id', 'original_filename', 'file_path', 'mime_type',
        'file_size', 'detected_processor', 'detected_statement_type',
        'processing_status', 'confidence_score', 'uploaded_by',
        'uploaded_at', 'processed_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'confidence_score' => 'decimal:2',
        'file_size' => 'integer',
    ];

    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
    public function summary() { return $this->hasOne(MerchantStatementSummary::class, 'merchant_statement_upload_id'); }
    public function lineItems() { return $this->hasMany(MerchantStatementLineItem::class, 'merchant_statement_upload_id'); }
    public function importBatches() { return $this->hasMany(MerchantImportBatch::class, 'merchant_statement_upload_id'); }
    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function scopePending($query) { return $query->where('processing_status', 'pending'); }
    public function scopeImported($query) { return $query->where('processing_status', 'imported'); }
}
