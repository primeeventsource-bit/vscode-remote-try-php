<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOnboardingProgress extends Model
{
    protected $table = 'user_onboarding_progress';
    protected $fillable = ['user_id', 'flow_id', 'step_id', 'status', 'started_at', 'completed_at', 'skipped_at', 'last_viewed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime', 'skipped_at' => 'datetime', 'last_viewed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function flow() { return $this->belongsTo(OnboardingFlow::class, 'flow_id'); }
    public function step() { return $this->belongsTo(OnboardingStep::class, 'step_id'); }
}
