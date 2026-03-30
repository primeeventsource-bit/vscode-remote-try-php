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
    ];

    protected $casts = [
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
