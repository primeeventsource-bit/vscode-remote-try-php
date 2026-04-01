<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTransfer extends Model
{
    protected $table = 'lead_transfers';

    protected $fillable = [
        'lead_id', 'from_user_id', 'to_user_id', 'transferred_by_user_id',
        'transfer_type', 'transfer_reason', 'disposition_snapshot', 'notes',
    ];

    public function lead() { return $this->belongsTo(Lead::class); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
    public function transferredBy() { return $this->belongsTo(User::class, 'transferred_by_user_id'); }
}
