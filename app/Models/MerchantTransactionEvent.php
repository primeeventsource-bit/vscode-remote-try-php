<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTransactionEvent extends Model
{
    protected $fillable = ['merchant_transaction_id', 'event_type', 'old_status', 'new_status', 'payload_json'];
    protected $casts = ['payload_json' => 'array'];
    public function transaction() { return $this->belongsTo(MerchantTransaction::class, 'merchant_transaction_id'); }
}
