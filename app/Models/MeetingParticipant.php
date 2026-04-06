<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id', 'user_id', 'role', 'invite_status',
        'attendance_status', 'joined_at', 'left_at',
        'audio_enabled', 'video_enabled', 'screen_sharing',
        'participant_identity',
    ];

    protected $casts = [
        'audio_enabled'  => 'boolean',
        'video_enabled'  => 'boolean',
        'screen_sharing' => 'boolean',
        'joined_at'      => 'datetime',
        'left_at'        => 'datetime',
    ];

    public function meeting() { return $this->belongsTo(Meeting::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function markJoined(): void
    {
        $this->update([
            'invite_status'     => 'accepted',
            'attendance_status' => 'joined',
            'joined_at'         => now(),
        ]);
    }

    public function markLeft(): void
    {
        $this->update(['attendance_status' => 'left', 'left_at' => now()]);
    }
}
