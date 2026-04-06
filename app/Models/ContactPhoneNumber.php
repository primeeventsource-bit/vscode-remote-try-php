<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactPhoneNumber extends Model
{
    protected $fillable = [
        'phoneable_type', 'phoneable_id', 'label', 'raw_phone',
        'normalized_phone', 'national_phone', 'country_code',
        'is_primary', 'is_sms_capable', 'is_voice_capable',
        'validation_status', 'last_validated_at', 'created_by',
    ];

    protected $casts = [
        'is_primary'       => 'boolean',
        'is_sms_capable'   => 'boolean',
        'is_voice_capable' => 'boolean',
        'last_validated_at' => 'datetime',
    ];

    public function phoneable(): MorphTo { return $this->morphTo(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
