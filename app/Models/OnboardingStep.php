<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    protected $fillable = ['flow_id', 'key', 'title', 'description', 'target_route', 'target_selector', 'icon', 'help_link', 'sort_order', 'is_required'];
    protected $casts = ['is_required' => 'boolean'];

    public function flow() { return $this->belongsTo(OnboardingFlow::class, 'flow_id'); }
}
