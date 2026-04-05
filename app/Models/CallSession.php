<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallSession extends Model
{
    protected $fillable = ['user_id', 'lead_id', 'deal_id', 'current_stage', 'objection_count', 'status', 'notes'];

    public function user() { return $this->belongsTo(User::class); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function deal() { return $this->belongsTo(Deal::class); }
    public function objectionLogs() { return $this->hasMany(ObjectionLog::class, 'call_session_id'); }
}
