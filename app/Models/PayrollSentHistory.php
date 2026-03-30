<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSentHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_sent_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'week_start',
        'week_label',
        'final_pay',
        'sent_by',
        'sent_at',
        'entry_snapshot',
    ];

    protected $casts = [
        'week_start' => 'date',
        'final_pay' => 'decimal:2',
        'sent_at' => 'datetime',
    ];
}
