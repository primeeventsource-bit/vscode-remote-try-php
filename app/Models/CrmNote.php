<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmNote extends Model
{
    protected $table = 'crm_notes';

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'body',
        'created_by_user_id',
        'updated_by_user_id',
        'sent_to_chat_at',
        'sent_to_chat_by_user_id',
        'internal_only',
    ];

    protected $casts = [
        'internal_only' => 'boolean',
        'sent_to_chat_at' => 'datetime',
    ];

    public function noteable()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function sentToChatBy()
    {
        return $this->belongsTo(User::class, 'sent_to_chat_by_user_id');
    }

    /**
     * Whether this note has been edited after creation.
     */
    public function getWasEditedAttribute(): bool
    {
        return $this->updated_at && $this->updated_at->gt($this->created_at->addSeconds(2));
    }
}
