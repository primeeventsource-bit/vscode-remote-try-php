<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chats';

    protected $fillable = [
        'name', 'type', 'members', 'icon_path', 'icon_emoji', 'created_by', 'pinned', 'deleted_by',
    ];

    protected $casts = [
        'members' => 'array',
        'pinned'  => 'boolean',
    ];

    public function canDelete(?User $user): bool
    {
        if (!$user) return false;
        if (in_array($user->role, ['admin', 'master_admin'], true)) return true;
        return (int) $this->created_by === (int) $user->id;
    }

    // ── Relationships ──────────────────────────────

    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function participants(): HasMany { return $this->hasMany(ChatParticipant::class); }
    public function activeParticipants(): HasMany { return $this->hasMany(ChatParticipant::class)->whereNull('left_at'); }
    public function latestMessage(): HasOne { return $this->hasOne(Message::class)->latestOfMany(); }

    // ── Type Helpers ───────────────────────────────

    public function isDirect(): bool { return $this->type === 'dm' || $this->type === 'direct'; }
    public function isGroup(): bool { return $this->type === 'group'; }

    // ── Member Access (hybrid: prefers chat_participants, falls back to JSON) ──

    public function getMemberIds(): array
    {
        try {
            $ids = $this->activeParticipants()->pluck('user_id')->toArray();
            if (!empty($ids)) return $ids;
        } catch (\Throwable $e) {}

        $members = $this->members;
        if (is_string($members)) $members = json_decode($members, true) ?? [];
        return array_map('intval', $members ?: []);
    }

    public function hasMember(int $userId): bool
    {
        return in_array($userId, $this->getMemberIds());
    }

    public function syncParticipants(): void
    {
        $members = is_string($this->members) ? json_decode($this->members, true) ?? [] : ($this->members ?: []);
        foreach ($members as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) continue;
            ChatParticipant::firstOrCreate(
                ['chat_id' => $this->id, 'user_id' => $uid],
                ['role_in_chat' => $this->created_by == $uid ? 'host' : 'member', 'joined_at' => $this->created_at ?? now()]
            );
        }
    }
}
