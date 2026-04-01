<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'reactions' => 'array',
        'metadata' => 'array',
        'is_system' => 'boolean',
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
}
