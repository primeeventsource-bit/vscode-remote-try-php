<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantImportBatch extends Model
{
    protected $fillable = [
        'merchant_statement_upload_id', 'merchant_account_id', 'import_type',
        'total_rows', 'imported_rows', 'failed_rows', 'duplicate_rows',
        'status', 'created_by',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'failed_rows' => 'integer',
        'duplicate_rows' => 'integer',
    ];

    public function upload() { return $this->belongsTo(MerchantStatementUpload::class, 'merchant_statement_upload_id'); }
    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
    public function failures() { return $this->hasMany(MerchantImportFailure::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
