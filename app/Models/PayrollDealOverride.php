<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollDealOverride extends Model
{
    use HasFactory;

    protected $table = 'payroll_deal_overrides';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'deal_id',
        'week_start',
        'override_type',
        'override_value',
    ];

    protected $casts = [
        'week_start' => 'date',
    ];
}
