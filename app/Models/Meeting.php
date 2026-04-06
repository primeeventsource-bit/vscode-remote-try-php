<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Meeting extends Model
{
    protected $fillable = [
        'uuid', 'type', 'source_type', 'source_id', 'provider',
        'provider_room_name', 'provider_room_sid', 'title',
        'host_user_id', 'status', 'started_at', 'ended_at',
        'duration_seconds', 'max_participants', 'settings',
    ];

    protected $casts = [
        'settings'   => 'array',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->uuid) $m->uuid = (string) Str::uuid();
        });
    }

    public function host() { return $this->belongsTo(User::class, 'host_user_id'); }
    public function participants() { return $this->hasMany(MeetingParticipant::class); }
    public function events() { return $this->hasMany(MeetingEvent::class); }

    public function activeParticipants()
    {
        return $this->participants()->where('attendance_status', 'joined');
    }

    public function isLive(): bool { return $this->status === 'live'; }
    public function isEnded(): bool { return in_array($this->status, ['ended', 'declined', 'missed', 'failed']); }
    public function isRinging(): bool { return $this->status === 'ringing'; }

    public function markLive(): void
    {
        if (! $this->started_at) {
            $this->update(['status' => 'live', 'started_at' => now()]);
        }
    }

    public function markEnded(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;
        $this->update(['status' => 'ended', 'ended_at' => now(), 'duration_seconds' => $duration]);
        $this->activeParticipants()->update(['attendance_status' => 'left', 'left_at' => now()]);
    }
}
