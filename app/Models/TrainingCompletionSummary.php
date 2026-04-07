<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingCompletionSummary extends Model
{
    protected $table = 'training_completion_summary';

    protected $fillable = [
        'user_id', 'flow_id', 'current_step_id',
        'progress_percent', 'started_at', 'completed_at', 'last_viewed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function flow() { return $this->belongsTo(OnboardingFlow::class, 'flow_id'); }
    public function currentStep() { return $this->belongsTo(OnboardingStep::class, 'current_step_id'); }
}
