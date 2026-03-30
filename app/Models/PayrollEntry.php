<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use HasFactory;

    protected $table = 'payroll_entries';

    protected $fillable = [
        'run_id',
        'user_id',
        'user_name',
        'user_role',
        'pay_type',
        'total_sold',
        'total_payout',
        'vd_taken',
        'commission_pct',
        'commission_amount',
        'fronter_cut',
        'snr_amount',
        'hourly_hours',
        'hourly_rate',
        'hourly_pay',
        'gross_pay',
        'cb_total',
        'net_pay',
        'final_pay',
        'deal_count',
        'cb_count',
        'vd_count',
        'deals_json',
        'status',
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'total_sold' => 'decimal:2',
        'total_payout' => 'decimal:2',
        'vd_taken' => 'decimal:2',
        'commission_pct' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'fronter_cut' => 'decimal:2',
        'snr_amount' => 'decimal:2',
        'hourly_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'hourly_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'cb_total' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'final_pay' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }

    public function adjustments()
    {
        return $this->hasMany(PayrollAdjustment::class, 'entry_id');
    }
}
