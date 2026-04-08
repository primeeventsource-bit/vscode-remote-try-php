<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollBatchV2 extends Model
{
    protected $table = 'payroll_batches_v2';

    protected $fillable = [
        'batch_name', 'period_start', 'period_end', 'batch_status',
        'total_gross', 'total_commissions', 'total_processing',
        'total_reserve', 'total_marketing', 'total_company_net',
        'created_by', 'approved_by', 'locked_by', 'paid_by',
        'approved_at', 'locked_at', 'paid_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_gross' => 'decimal:2',
        'total_commissions' => 'decimal:2',
        'total_processing' => 'decimal:2',
        'total_reserve' => 'decimal:2',
        'total_marketing' => 'decimal:2',
        'total_company_net' => 'decimal:2',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function items() { return $this->hasMany(PayrollBatchItem::class, 'payroll_batch_id'); }
    public function batchDeals() { return $this->hasMany(PayrollBatchDeal::class, 'payroll_batch_id'); }
    public function createdByUser() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeDraft($query) { return $query->where('batch_status', 'draft'); }
    public function scopeApproved($query) { return $query->where('batch_status', 'approved'); }
}
