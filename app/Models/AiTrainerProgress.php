<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainerProgress extends Model
{
    protected $table = 'ai_trainer_progress';

    protected $fillable = [
        'user_id', 'role', 'strengths_json', 'weaknesses_json',
        'total_hints_shown', 'total_mistakes_detected',
        'total_recommendations_completed', 'note_quality_avg', 'last_coached_at',
    ];

    protected $casts = [
        'strengths_json' => 'array',
        'weaknesses_json' => 'array',
        'last_coached_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
