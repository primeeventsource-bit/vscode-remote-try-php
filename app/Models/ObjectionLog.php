<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObjectionLog extends Model
{
    protected $fillable = ['call_session_id', 'objection_id', 'objection_text', 'selected_rebuttal', 'rebuttal_level', 'result', 'user_id'];

    public function session() { return $this->belongsTo(CallSession::class, 'call_session_id'); }
    public function objection() { return $this->belongsTo(Objection::class, 'objection_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
