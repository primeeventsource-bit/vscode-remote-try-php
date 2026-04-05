<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VideoRoom extends Model
{
    protected $fillable = [
        'uuid', 'name', 'created_by', 'type', 'chat_id', 'status', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $room) {
            if (!$room->uuid) $room->uuid = (string) Str::uuid();
        });
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function chat() { return $this->belongsTo(Chat::class, 'chat_id'); }

    public function isDirect(): bool { return $this->type === 'direct'; }
    public function isGroup(): bool { return $this->type === 'group'; }
    public function participants() { return $this->hasMany(VideoRoomParticipant::class, 'room_id'); }
    public function logs() { return $this->hasMany(VideoCallLog::class, 'room_id'); }
    public function signals() { return $this->hasMany(VideoSignal::class, 'room_id'); }

    public function isWaiting(): bool { return $this->status === 'waiting'; }
    public function isActive(): bool { return $this->status === 'active'; }
    public function isEnded(): bool { return $this->status === 'ended'; }

    public function canBeJoinedBy(User $user): bool
    {
        if ($this->isEnded()) return false;
        return $this->participants()->where('user_id', $user->id)
            ->whereIn('invite_status', ['pending', 'accepted'])->exists();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public function activeParticipants()
    {
        return $this->participants()->whereNotNull('joined_at')->whereNull('left_at');
    }

    public function markStarted(): void
    {
        if (!$this->started_at) $this->update(['status' => 'active', 'started_at' => now()]);
    }

    public function markEnded(): void
    {
        $this->update(['status' => 'ended', 'ended_at' => now()]);
        $this->activeParticipants()->update(['left_at' => now()]);
    }
}
