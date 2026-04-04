<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $table = 'call_logs';

    protected $fillable = [
        'user_id', 'callable_type', 'callable_id', 'record_type', 'record_id',
        'contact_name', 'raw_phone', 'normalized_phone', 'extension',
        'launch_method', 'generated_href', 'status', 'outcome', 'notes',
        'initiated_at', 'ended_at', 'duration_seconds', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'initiated_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function callable() { return $this->morphTo(); }

    public function scopeRecent($q, int $limit = 10) { return $q->orderByDesc('initiated_at')->limit($limit); }
    public function scopeForUser($q, int $userId) { return $q->where('user_id', $userId); }
    public function scopeConnected($q) { return $q->where('outcome', 'connected'); }
}
