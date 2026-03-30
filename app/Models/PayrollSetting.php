<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    protected $table = 'payroll_settings';

    const CREATED_AT = null;

    protected $fillable = [
        'closer_pct',
        'fronter_pct',
        'snr_pct',
        'vd_pct',
        'admin_snr_pct',
        'hourly_rate',
        'updated_by',
    ];

    protected $casts = [
        'closer_pct' => 'decimal:2',
        'fronter_pct' => 'decimal:2',
        'snr_pct' => 'decimal:2',
        'vd_pct' => 'decimal:2',
        'admin_snr_pct' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];
}
