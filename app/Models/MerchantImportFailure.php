<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantImportFailure extends Model
{
    protected $fillable = [
        'merchant_import_batch_id', 'row_number', 'error_type',
        'error_message', 'row_data_json',
    ];

    protected $casts = ['row_data_json' => 'array'];

    public function batch() { return $this->belongsTo(MerchantImportBatch::class, 'merchant_import_batch_id'); }
}
