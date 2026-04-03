<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPayroll extends Model
{
    protected $table = 'admin_payroll';

    protected $fillable = [
        'admin_user_id', 'entered_by_user_id', 'pay_period_start', 'pay_period_end',
        'hours_worked', 'hourly_rate', 'commission_bonus', 'deductions',
        'total_check_pay', 'notes', 'finalized',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'hours_worked' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'commission_bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'total_check_pay' => 'decimal:2',
        'finalized' => 'boolean',
    ];

    public function admin() { return $this->belongsTo(User::class, 'admin_user_id'); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by_user_id'); }
}
