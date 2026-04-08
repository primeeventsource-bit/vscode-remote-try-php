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
        'avatar',
        'color',
        'status',
        'username',
        'password',
        'avatar_path',
        'avatar_emoji',
        'presence_status',
        'last_seen_at',
        'last_active_at',
        'idle_since_at',
    ];

    // role and permissions are NOT in $fillable — set them explicitly:
    // $user->role = 'admin'; $user->permissions = [...]; $user->save();

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

    // ── Location helpers ────────────────────────────────────

    public function getLocationAttribute($value): string
    {
        if ($value) return $value;
        return str_contains($this->role ?? '', 'panama') ? 'Panama' : 'US';
    }

    public function baseRole(): string
    {
        return str_replace('_panama', '', $this->role ?? '');
    }

    public function isFronter(): bool
    {
        return in_array($this->role, ['fronter', 'fronter_panama']);
    }

    public function isCloser(): bool
    {
        return in_array($this->role, ['closer', 'closer_panama']);
    }

    public function isAgent(): bool
    {
        return $this->isFronter() || $this->isCloser();
    }

    public function isPanama(): bool
    {
        return $this->location === 'Panama';
    }

    public function isUS(): bool
    {
        return $this->location === 'US';
    }

    public function roleLocationLabel(): string
    {
        $base = ucfirst($this->baseRole());
        $loc = $this->location;
        return "{$base} ({$loc})";
    }

    // ── Relationships ───────────────────────────────────────

    public function agentStats()
    {
        return $this->hasMany(AgentStatDaily::class);
    }

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
