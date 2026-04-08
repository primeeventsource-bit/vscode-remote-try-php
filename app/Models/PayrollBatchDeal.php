<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollBatchDeal extends Model
{
    protected $fillable = ['payroll_batch_id', 'deal_id', 'deal_financial_id'];

    public function batch() { return $this->belongsTo(PayrollBatchV2::class, 'payroll_batch_id'); }
    public function deal() { return $this->belongsTo(Deal::class); }
    public function dealFinancial() { return $this->belongsTo(DealFinancial::class); }
}
