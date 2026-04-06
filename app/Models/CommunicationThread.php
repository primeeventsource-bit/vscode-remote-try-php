<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationThread extends Model
{
    protected $fillable = [
        'subject', 'threadable_type', 'threadable_id', 'channel',
        'assigned_user_id', 'phone_number', 'last_message_at',
        'last_inbound_at', 'last_outbound_at', 'unread_count',
        'status', 'created_by',
    ];

    protected $casts = [
        'last_message_at'  => 'datetime',
        'last_inbound_at'  => 'datetime',
        'last_outbound_at' => 'datetime',
    ];

    public function threadable(): MorphTo { return $this->morphTo(); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function communications() { return $this->hasMany(Communication::class, 'thread_id')->orderBy('created_at'); }
    public function latestCommunication() { return $this->hasOne(Communication::class, 'thread_id')->latestOfMany(); }

    public function scopeOpen($q) { return $q->where('status', 'open'); }
    public function scopeForEntity($q, string $type, int $id) { return $q->where('threadable_type', $type)->where('threadable_id', $id); }
}
