<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAdminHour extends Model
{
    use HasFactory;

    protected $table = 'payroll_admin_hours';

    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'week_start',
        'hours',
    ];

    protected $casts = [
        'week_start' => 'date',
        'hours' => 'decimal:2',
    ];
}
