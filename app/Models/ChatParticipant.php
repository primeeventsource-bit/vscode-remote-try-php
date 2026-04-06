<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatParticipant extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'role_in_chat',
        'last_read_message_id',
        'last_read_at',
        'notifications_muted',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'notifications_muted' => 'boolean',
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    // ── Scopes ─────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ─────────────────────────────────────

    public function hasUnreadMessages(): bool
    {
        if (!$this->last_read_message_id) return true;

        return Message::where('chat_id', $this->chat_id)
            ->where('id', '>', $this->last_read_message_id)
            ->where('sender_id', '!=', $this->user_id)
            ->exists();
    }

    public function unreadCount(): int
    {
        if (!$this->last_read_message_id) {
            return Message::where('chat_id', $this->chat_id)
                ->where('sender_id', '!=', $this->user_id)
                ->count();
        }

        return Message::where('chat_id', $this->chat_id)
            ->where('id', '>', $this->last_read_message_id)
            ->where('sender_id', '!=', $this->user_id)
            ->count();
    }
}
