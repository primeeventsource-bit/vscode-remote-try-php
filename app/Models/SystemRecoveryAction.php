<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRecoveryAction extends Model
{
    protected $fillable = [
        'incident_id', 'action', 'status', 'requires_approval',
        'approved_by', 'result', 'retry_count', 'max_retries',
        'last_attempt_at', 'cooldown_until',
    ];

    protected $casts = [
        'result'            => 'array',
        'requires_approval' => 'boolean',
        'last_attempt_at'   => 'datetime',
        'cooldown_until'    => 'datetime',
    ];

    public function incident() { return $this->belongsTo(SystemIncident::class, 'incident_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
