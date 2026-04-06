<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationEvent extends Model
{
    protected $fillable = [
        'communication_id', 'provider', 'event_type',
        'provider_sid', 'payload', 'processed_at',
        'processing_status', 'error_message',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function communication() { return $this->belongsTo(Communication::class); }
}
