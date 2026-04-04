<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'resort',
        'owner_name',
        'phone1',
        'phone2',
        'city',
        'st',
        'zip',
        'resort_location',
        'assigned_to',
        'original_fronter',
        'disposition',
        'transferred_to',
        'source',
        'callback_date',
        // Pipeline tracking fields
        'current_stage',
        'transferred_by_user_id',
        'transferred_to_user_id',
        'transferred_at',
        'closer_received_at',
        'closed_by_user_id',
        'closed_at',
        'converted_to_deal_id',
        'sent_to_verification_by_user_id',
        'sent_to_verification_at',
        'verification_received_by_user_id',
        'verification_received_at',
        'final_outcome',
        'final_outcome_at',
    ];

    protected $casts = [
        'callback_date' => 'datetime',
        'transferred_at' => 'datetime',
        'closer_received_at' => 'datetime',
        'closed_at' => 'datetime',
        'sent_to_verification_at' => 'datetime',
        'verification_received_at' => 'datetime',
        'final_outcome_at' => 'datetime',
    ];

    // ── Existing relationships ──────────────────────────────

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function originalFronter()
    {
        return $this->belongsTo(User::class, 'original_fronter');
    }

    public function transfers()
    {
        return $this->hasMany(LeadTransfer::class);
    }

    public function transferredToUser()
    {
        return $this->belongsTo(User::class, 'transferred_to');
    }

    // ── Pipeline relationships ──────────────────────────────

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }

    public function transferredToCloser()
    {
        return $this->belongsTo(User::class, 'transferred_to_user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sentToVerificationBy()
    {
        return $this->belongsTo(User::class, 'sent_to_verification_by_user_id');
    }

    public function verificationReceivedBy()
    {
        return $this->belongsTo(User::class, 'verification_received_by_user_id');
    }

    public function convertedDeal()
    {
        return $this->belongsTo(Deal::class, 'converted_to_deal_id');
    }

    public function deal()
    {
        return $this->hasOne(Deal::class, 'lead_id');
    }

    public function pipelineEvents()
    {
        return $this->hasMany(PipelineEvent::class);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeTransferred($query)
    {
        return $query->where('disposition', 'Transferred to Closer');
    }

    public function scopeClosed($query)
    {
        return $query->where('disposition', 'Converted to Deal');
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('current_stage', $stage);
    }

    public function scopeByOutcome($query, string $outcome)
    {
        return $query->where('final_outcome', $outcome);
    }
}
