<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealCloser extends Model
{
    protected $table = 'deal_closers';

    protected $fillable = [
        'deal_id', 'user_id', 'comm_pct', 'comm_amount',
        'snr_deduction', 'vd_deduction', 'net_pay', 'is_original',
    ];

    protected $casts = [
        'is_original' => 'boolean',
        'comm_pct' => 'decimal:2',
        'comm_amount' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function deal() { return $this->belongsTo(Deal::class); }
    public function user() { return $this->belongsTo(User::class); }
}
