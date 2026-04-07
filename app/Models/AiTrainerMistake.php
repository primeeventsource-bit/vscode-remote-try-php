<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainerMistake extends Model
{
    protected $fillable = [
        'user_id', 'module', 'entity_type', 'entity_id',
        'mistake_type', 'severity', 'message',
        'details_json', 'detected_at', 'resolved_at',
    ];

    protected $casts = [
        'details_json' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeUnresolved($q) { return $q->whereNull('resolved_at'); }
}
