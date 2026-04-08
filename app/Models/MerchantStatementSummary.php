<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantStatementSummary extends Model
{
    protected $fillable = [
        'merchant_statement_upload_id', 'merchant_account_id',
        'statement_start_date', 'statement_end_date',
        'gross_volume', 'net_volume', 'refunds_total', 'chargebacks_total',
        'fees_total', 'reserves_total', 'payouts_total', 'ending_balance',
        'raw_summary_json',
    ];

    protected $casts = [
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'gross_volume' => 'decimal:2',
        'net_volume' => 'decimal:2',
        'refunds_total' => 'decimal:2',
        'chargebacks_total' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'reserves_total' => 'decimal:2',
        'payouts_total' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'raw_summary_json' => 'array',
    ];

    public function upload() { return $this->belongsTo(MerchantStatementUpload::class, 'merchant_statement_upload_id'); }
    public function merchantAccount() { return $this->belongsTo(MerchantAccount::class); }
}
