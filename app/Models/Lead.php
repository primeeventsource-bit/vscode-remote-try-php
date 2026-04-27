<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'resort',
        'owner_name',
        'owner_name_2',
        'phone1',
        'phone2',
        'city',
        'st',
        'zip',
        'resort_location',
        'email',
        'description',
        'assigned_to',
        'original_fronter',
        'disposition',
        'transferred_to',
        'source',
        'source_file_name',
        'callback_date',
        'imported_at',
        'import_batch_id',
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
        'imported_at' => 'datetime',
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

    // ── Enterprise relationships ────────────────────────────

    public function importBatch()
    {
        return $this->belongsTo(LeadImportBatch::class, 'import_batch_id');
    }

    public function duplicatesAsOriginal()
    {
        return $this->hasMany(LeadDuplicate::class, 'lead_id');
    }

    public function duplicatesAsDuplicate()
    {
        return $this->hasMany(LeadDuplicate::class, 'duplicate_lead_id');
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

    // ── Age filter scopes ───────────────────────────────────

    public function scopeNewToday($query)
    {
        return $query->where('created_at', '>=', now()->startOfDay());
    }

    public function scopeThisWeek($query)
    {
        return $query->where('created_at', '>=', now()->startOfWeek());
    }

    public function scopeLastMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth(),
        ]);
    }

    public function scopeOlderThanLastMonth($query)
    {
        return $query->where('created_at', '<', now()->subMonth()->startOfMonth());
    }

    // ── Duplicate scopes ────────────────────────────────────

    public function scopeHasDuplicates($query)
    {
        return $query->whereHas('duplicatesAsOriginal');
    }

    public function scopeHasExactDuplicates($query)
    {
        return $query->whereHas('duplicatesAsOriginal', fn($q) => $q->where('duplicate_type', 'exact'));
    }

    public function scopeHasPossibleDuplicates($query)
    {
        return $query->whereHas('duplicatesAsOriginal', fn($q) => $q->where('duplicate_type', 'possible'));
    }

    // ── Helpers ─────────────────────────────────────────────

    public function normalizedPhone1(): ?string
    {
        return $this->phone1 ? preg_replace('/[^0-9]/', '', $this->phone1) : null;
    }

    public function normalizedPhone2(): ?string
    {
        return $this->phone2 ? preg_replace('/[^0-9]/', '', $this->phone2) : null;
    }

    public function normalizedEmail(): ?string
    {
        return $this->email ? strtolower(trim($this->email)) : null;
    }
}
