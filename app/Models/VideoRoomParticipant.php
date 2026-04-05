<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoRoomParticipant extends Model
{
    protected $fillable = [
        'room_id', 'user_id', 'invited_by', 'role', 'invite_status',
        'joined_at', 'left_at', 'mic_enabled', 'camera_enabled',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'mic_enabled' => 'boolean',
        'camera_enabled' => 'boolean',
    ];

    public function room() { return $this->belongsTo(VideoRoom::class, 'room_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function inviter() { return $this->belongsTo(User::class, 'invited_by'); }

    public function markJoined(): void { $this->update(['invite_status' => 'accepted', 'joined_at' => now()]); }
    public function markLeft(): void { $this->update(['left_at' => now()]); }
    public function markAccepted(): void { $this->update(['invite_status' => 'accepted']); }
    public function markDeclined(): void { $this->update(['invite_status' => 'declined']); }
}
