<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealingAction extends Model
{
    protected $fillable = [
        'subsystem', 'action', 'trigger', 'status',
        'input', 'result', 'retry_count',
        'triggered_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'input'        => 'array',
        'result'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function scopeRecent($q, int $hours = 24)
    {
        return $q->where('created_at', '>', now()->subHours($hours));
    }
}
