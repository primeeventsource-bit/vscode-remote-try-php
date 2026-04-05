<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineEvent extends Model
{
    protected $table = 'pipeline_events';

    // Event type constants — the source of truth for statistics
    public const TRANSFERRED_TO_CLOSER = 'transferred_to_closer';
    public const CLOSER_RECEIVED = 'closer_received';
    public const CLOSER_CLOSED_DEAL = 'closer_closed_deal';
    public const CLOSER_NOT_CLOSED = 'closer_not_closed';
    public const SENT_TO_VERIFICATION = 'sent_to_verification';
    public const VERIFICATION_RECEIVED = 'verification_received';
    public const VERIFICATION_CHARGED_GREEN = 'verification_charged_green';
    public const VERIFICATION_NOT_CHARGED = 'verification_not_charged';
    public const CLOSER_TRANSFERRED_TO_CLOSER = 'closer_transferred_to_closer';

    protected $fillable = [
        'lead_id',
        'deal_id',
        'event_type',
        'from_stage',
        'to_stage',
        'performed_by_user_id',
        'source_user_id',
        'target_user_id',
        'source_role',
        'target_role',
        'success_flag',
        'outcome',
        'notes',
        'metadata',
        'event_at',
    ];

    protected $casts = [
        'success_flag' => 'boolean',
        'metadata' => 'array',
        'event_at' => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeInRange($query, $from = null, $to = null)
    {
        if ($from) $query->where('event_at', '>=', $from);
        if ($to) $query->where('event_at', '<=', $to);
        return $query;
    }

    public function scopeForSourceUser($query, int $userId)
    {
        return $query->where('source_user_id', $userId);
    }

    public function scopeForTargetUser($query, int $userId)
    {
        return $query->where('target_user_id', $userId);
    }

    public function scopeForPerformer($query, int $userId)
    {
        return $query->where('performed_by_user_id', $userId);
    }
}
