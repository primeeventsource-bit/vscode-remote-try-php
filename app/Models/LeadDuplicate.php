<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDuplicate extends Model
{
    protected $fillable = [
        'lead_id',
        'duplicate_lead_id',
        'duplicate_type',
        'duplicate_reason',
        'matched_fields',
        'detected_at',
        'reviewed_by',
        'review_status',
    ];

    protected $casts = [
        'matched_fields' => 'array',
        'detected_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id')->withTrashed();
    }

    public function duplicateLead()
    {
        return $this->belongsTo(Lead::class, 'duplicate_lead_id')->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->review_status === 'pending';
    }

    public function scopePending($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function scopeExact($query)
    {
        return $query->where('duplicate_type', 'exact');
    }

    public function scopePossible($query)
    {
        return $query->where('duplicate_type', 'possible');
    }
}
