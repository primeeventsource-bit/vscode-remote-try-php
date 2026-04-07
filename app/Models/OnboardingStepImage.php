<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingStepImage extends Model
{
    protected $table = 'onboarding_step_images';

    protected $fillable = ['step_id', 'image_path', 'caption', 'sort_order'];

    public function step() { return $this->belongsTo(OnboardingStep::class, 'step_id'); }
}
