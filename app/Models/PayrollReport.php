<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollReport extends Model
{
    protected $table = 'payroll_reports';

    protected $fillable = [
        'week_label', 'week_start', 'week_end', 'user_id', 'user_role',
        'total_deals_amount', 'total_commission', 'total_snr', 'total_vd',
        'net_pay', 'deal_count', 'deal_details', 'status', 'generated_by', 'finalized_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'deal_details' => 'array',
        'finalized_at' => 'datetime',
        'total_deals_amount' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'total_snr' => 'decimal:2',
        'total_vd' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function generator() { return $this->belongsTo(User::class, 'generated_by'); }
}
