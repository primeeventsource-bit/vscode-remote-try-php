<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoRoomInvite extends Model
{
    protected $fillable = [
        'video_room_id', 'invited_user_id', 'invited_by_user_id',
        'invite_type', 'invite_status', 'delivered_at', 'responded_at',
    ];

    protected $casts = [
        'delivered_at'  => 'datetime',
        'responded_at'  => 'datetime',
    ];

    public function room() { return $this->belongsTo(VideoRoom::class, 'video_room_id'); }
    public function invitedUser() { return $this->belongsTo(User::class, 'invited_user_id'); }
    public function invitedBy() { return $this->belongsTo(User::class, 'invited_by_user_id'); }

    public function scopePending($q) { return $q->where('invite_status', 'pending'); }
}
