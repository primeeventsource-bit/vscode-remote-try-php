<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Communication extends Model
{
    protected $fillable = [
        'thread_id', 'communicable_type', 'communicable_id',
        'contact_phone_number_id', 'user_id', 'provider',
        'provider_message_sid', 'provider_call_sid',
        'channel', 'direction', 'message_type',
        'to_phone', 'from_phone', 'body',
        'media_count', 'media_urls', 'status',
        'error_code', 'error_message',
        'sent_at', 'delivered_at', 'failed_at', 'received_at',
        'metadata', 'created_by',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'metadata'   => 'array',
        'sent_at'    => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at'  => 'datetime',
        'received_at' => 'datetime',
    ];

    public function thread() { return $this->belongsTo(CommunicationThread::class, 'thread_id'); }
    public function communicable(): MorphTo { return $this->morphTo(); }
    public function contactPhone() { return $this->belongsTo(ContactPhoneNumber::class, 'contact_phone_number_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function events() { return $this->hasMany(CommunicationEvent::class); }

    public function scopeInbound($q) { return $q->where('direction', 'inbound'); }
    public function scopeOutbound($q) { return $q->where('direction', 'outbound'); }
    public function scopeFailed($q) { return $q->where('status', 'failed'); }

    public function isDelivered(): bool { return $this->status === 'delivered'; }
    public function isFailed(): bool { return in_array($this->status, ['failed', 'undelivered']); }
}
