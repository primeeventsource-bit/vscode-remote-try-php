<?php

namespace App\Models;

use App\Enums\CallInviteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallParticipant extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'invite_status',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'invite_status' => CallInviteStatus::class,
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('invite_status', [
            CallInviteStatus::Invited,
            CallInviteStatus::Ringing,
            CallInviteStatus::Accepted,
            CallInviteStatus::Joined,
        ]);
    }

    public function scopePending($query)
    {
        return $query->whereIn('invite_status', [
            CallInviteStatus::Invited,
            CallInviteStatus::Ringing,
        ]);
    }
}
