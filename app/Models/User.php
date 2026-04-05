<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'role',
        'avatar',
        'color',
        'status',
        'username',
        'password',
        'permissions',
        'avatar_path',
        'avatar_emoji',
        'presence_status',
        'last_seen_at',
        'last_active_at',
        'idle_since_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_seen_at' => 'datetime',
        'last_active_at' => 'datetime',
        'idle_since_at' => 'datetime',
    ];

    public function hasPerm(string $perm): bool
    {
        $perms = is_array($this->permissions) ? $this->permissions : json_decode($this->permissions ?? '[]', true);
        return in_array('master_override', $perms) || in_array($perm, $perms);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    // ── Presence helpers ────────────────────────────────────

    public function isOnline(): bool
    {
        return ($this->presence_status ?? 'offline') === 'online';
    }

    public function isIdle(): bool
    {
        return ($this->presence_status ?? 'offline') === 'idle';
    }

    public function isOffline(): bool
    {
        return !$this->isOnline() && !$this->isIdle();
    }

    public function presenceColor(): string
    {
        return match ($this->presence_status ?? 'offline') {
            'online' => '#10b981',
            'idle' => '#f59e0b',
            default => '#ef4444',
        };
    }

    public function presenceLabel(): string
    {
        return match ($this->presence_status ?? 'offline') {
            'online' => 'Online',
            'idle' => 'Idle ' . $this->formattedIdleDuration(),
            default => 'Offline',
        };
    }

    public function presenceDotClass(): string
    {
        return match ($this->presence_status ?? 'offline') {
            'online' => 'bg-emerald-500',
            'idle' => 'bg-amber-400',
            default => 'bg-red-400',
        };
    }

    public function formattedIdleDuration(): string
    {
        if (!$this->idle_since_at) return '';
        $diff = now()->diffInSeconds($this->idle_since_at);
        if ($diff < 60) return $diff . 's';
        if ($diff < 3600) return floor($diff / 60) . 'm';
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        return "{$h}h " . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
    }

    // ── Relationships ───────────────────────────────────────

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function fronterDeals()
    {
        return $this->hasMany(Deal::class, 'fronter');
    }

    public function closerDeals()
    {
        return $this->hasMany(Deal::class, 'closer');
    }

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class, 'user_id');
    }
}
