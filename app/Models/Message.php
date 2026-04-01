<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Message extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'messages';

    protected $fillable = [
        'chat_id',
        'sender_id',
        'message_type',
        'text',
        'file_url',
        'file_name',
        'gif_url',
        'gif_preview_url',
        'gif_provider',
        'gif_external_id',
        'gif_title',
        'metadata',
        'reactions',
        'is_system',
        'reply_to',
        'status',
        'delivered_at',
        'seen_at',
    ];

    protected $casts = [
        'reactions' => 'array',
        'metadata' => 'array',
        'is_system' => 'boolean',
        'delivered_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyToMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    // --- Status helpers ---

    public function isSent(): bool
    {
        return !$this->delivered_at && !$this->seen_at;
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at && !$this->seen_at;
    }

    public function isSeen(): bool
    {
        return (bool) $this->seen_at;
    }

    // --- Accessors ---

    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at?->format('g:i A') ?? '';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at?->format('F j, Y') ?? '';
    }

    public function getDeliveredTimeAttribute(): ?string
    {
        return $this->delivered_at?->format('g:i A');
    }

    public function getSeenTimeAttribute(): ?string
    {
        return $this->seen_at?->format('g:i A');
    }

    public function getSmartTimestampAttribute(): string
    {
        if (!$this->created_at) return '';

        if ($this->created_at->isToday()) {
            return $this->created_at->format('g:i A');
        }

        if ($this->created_at->isYesterday()) {
            return 'Yesterday ' . $this->created_at->format('g:i A');
        }

        return $this->created_at->format('M j, Y g:i A');
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->seen_at) {
            return 'Seen ' . $this->seen_at->format('g:i A');
        }

        if ($this->delivered_at) {
            return 'Delivered ' . $this->delivered_at->format('g:i A');
        }

        return 'Sent ' . ($this->created_at?->format('g:i A') ?? '');
    }
}
