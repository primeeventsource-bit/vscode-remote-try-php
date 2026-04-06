<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemIncident extends Model
{
    protected $fillable = [
        'component', 'severity', 'title', 'description', 'context', 'status',
        'fingerprint', 'assigned_to', 'resolved_by', 'opened_at',
        'acknowledged_at', 'resolved_at', 'resolution_notes',
    ];

    protected $casts = [
        'context'          => 'array',
        'opened_at'        => 'datetime',
        'acknowledged_at'  => 'datetime',
        'resolved_at'      => 'datetime',
    ];

    public function assignee()    { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver()    { return $this->belongsTo(User::class, 'resolved_by'); }
    public function recoveries()  { return $this->hasMany(SystemRecoveryAction::class, 'incident_id'); }

    public function scopeOpen($q)     { return $q->where('status', 'open'); }
    public function scopeCritical($q) { return $q->whereIn('severity', ['critical', 'system_breaking']); }
}
