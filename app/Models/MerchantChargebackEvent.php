<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantChargebackEvent extends Model
{
    protected $fillable = ['merchant_chargeback_id', 'event_type', 'old_status', 'new_status', 'payload_json'];
    protected $casts = ['payload_json' => 'array'];
    public function chargeback() { return $this->belongsTo(MerchantChargeback::class, 'merchant_chargeback_id'); }
}
