<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $table = 'deals';

    protected $fillable = [
        'timestamp',
        'charged_date',
        'was_vd',
        'fronter',
        'closer',
        'fee',
        'owner_name',
        'mailing_address',
        'city_state_zip',
        'primary_phone',
        'secondary_phone',
        'email',
        'weeks',
        'asking_rental',
        'resort_name',
        'resort_city_state',
        'exchange_group',
        'bed_bath',
        'usage',
        'asking_sale_price',
        'name_on_card',
        'card_type',
        'bank',
        'card_number',
        'exp_date',
        'cv2',
        'billing_address',
        'bank2',
        'card_number2',
        'exp_date2',
        'cv2_2',
        'using_timeshare',
        'looking_to_get_out',
        'verification_num',
        'notes',
        'login_info',
        'correspondence',
        'files',
        'snr',
        'login',
        'merchant',
        'app_login',
        'assigned_admin',
        'status',
        'charged',
        'charged_back',
        'closing_date',
        'disposition_status',
        'callback_date',
        'last_edited_by',
        'last_edited_at',
        'is_locked',
        'is_vd_deal',
        'fronter_role',
        'closer_comm_pct',
        'closer_comm_amount',
        'fronter_comm_amount',
        'snr_deduction',
        'vd_deduction',
        'closer_net_pay',
        'payroll_week',
        'payroll_finalized',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'callback_date' => 'datetime',
        'last_edited_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_vd_deal' => 'boolean',
        'payroll_finalized' => 'boolean',
        'closer_comm_pct' => 'decimal:2',
        'closer_comm_amount' => 'decimal:2',
        'fronter_comm_amount' => 'decimal:2',
        'snr_deduction' => 'decimal:2',
        'vd_deduction' => 'decimal:2',
        'closer_net_pay' => 'decimal:2',
        'timestamp' => 'date',
        'charged_date' => 'date',
        'correspondence' => 'array',
        'files' => 'array',
        'fee' => 'decimal:2',
    ];

    public function fronterUser()
    {
        return $this->belongsTo(User::class, 'fronter');
    }

    public function closerUser()
    {
        return $this->belongsTo(User::class, 'closer');
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'assigned_admin');
    }
}
