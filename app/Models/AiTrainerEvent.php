<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainerEvent extends Model
{
    protected $fillable = [
        'user_id', 'role', 'module', 'entity_type', 'entity_id',
        'event_type', 'context_json', 'ai_response_json', 'severity',
    ];

    protected $casts = [
        'context_json' => 'array',
        'ai_response_json' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
