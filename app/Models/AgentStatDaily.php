<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AgentStatDaily extends Model
{
    protected $table = 'agent_stats_daily';

    protected $fillable = [
        'user_id', 'role', 'location', 'stat_date',
        'leads_assigned', 'leads_contacted', 'leads_qualified',
        'leads_not_interested', 'leads_transferred', 'avg_first_contact_seconds',
        'deals_received', 'deals_closed', 'deals_lost',
        'revenue', 'avg_deal_value',
        'activity_count', 'tasks_completed', 'calls_made', 'sms_sent',
        'follow_up_count', 'follow_up_on_time',
        'notes_quality_score', 'objection_handling_score',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'revenue' => 'decimal:2',
        'avg_deal_value' => 'decimal:2',
        'notes_quality_score' => 'decimal:2',
        'objection_handling_score' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('agent_stats_daily');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function todayForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'stat_date' => now()->toDateString()],
            [
                'role' => $user->role,
                'location' => $user->location ?? self::inferLocation($user->role),
            ]
        );
    }

    public static function inferLocation(string $role): string
    {
        return str_contains($role, 'panama') ? 'Panama' : 'US';
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeForLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function scopeInRange($query, $from = null, $to = null)
    {
        if ($from) $query->where('stat_date', '>=', $from);
        if ($to) $query->where('stat_date', '<=', $to);
        return $query;
    }

    public function scopeForFronters($query)
    {
        return $query->whereIn('role', ['fronter', 'fronter_panama']);
    }

    public function scopeForClosers($query)
    {
        return $query->whereIn('role', ['closer', 'closer_panama']);
    }
}
