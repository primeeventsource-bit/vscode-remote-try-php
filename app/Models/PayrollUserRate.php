<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollUserRate extends Model
{
    use HasFactory;

    protected $table = 'payroll_user_rates';

    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'comm_pct',
        'snr_pct',
        'hourly_rate',
    ];

    protected $casts = [
        'comm_pct' => 'decimal:2',
        'snr_pct' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];
}
