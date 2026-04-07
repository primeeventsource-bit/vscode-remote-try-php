<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    protected $fillable = [
        'flow_id', 'key', 'title', 'description',
        'step_type', 'target_route', 'target_selector',
        'action_event', 'action_value', 'tooltip_position',
        'icon', 'help_link', 'image_path', 'image_caption', 'tip_text',
        'sort_order', 'is_required', 'is_enabled',
        'highlight_element', 'dim_background', 'auto_scroll',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_enabled' => 'boolean',
        'highlight_element' => 'boolean',
        'dim_background' => 'boolean',
        'auto_scroll' => 'boolean',
    ];

    public function flow() { return $this->belongsTo(OnboardingFlow::class, 'flow_id'); }
    public function images() { return $this->hasMany(OnboardingStepImage::class, 'step_id')->orderBy('sort_order'); }
}
