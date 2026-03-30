<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollManualDeal extends Model
{
    use HasFactory;

    protected $table = 'payroll_manual_deals';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'week_start',
        'customer_name',
        'amount',
        'deal_date',
        'was_vd',
        'created_by',
    ];

    protected $casts = [
        'week_start' => 'date',
        'amount' => 'decimal:2',
    ];
}
