<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingFlow extends Model
{
    protected $fillable = [
        'role', 'name', 'description', 'cover_image_path',
        'is_active', 'is_published', 'auto_start_on_first_login',
        'allow_skip', 'lock_ui_during_training',
        'version', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'auto_start_on_first_login' => 'boolean',
        'allow_skip' => 'boolean',
        'lock_ui_during_training' => 'boolean',
    ];

    public function steps() { return $this->hasMany(OnboardingStep::class, 'flow_id')->orderBy('sort_order'); }
    public function enabledSteps() { return $this->hasMany(OnboardingStep::class, 'flow_id')->where('is_enabled', true)->orderBy('sort_order'); }
    public function progress() { return $this->hasMany(UserOnboardingProgress::class, 'flow_id'); }
    public function completionSummaries() { return $this->hasMany(TrainingCompletionSummary::class, 'flow_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public static function forRole(string $role): ?self
    {
        return self::where('role', $role)->where('is_active', true)->where('is_published', true)->first();
    }

    public static function allPublished()
    {
        return self::where('is_active', true)->where('is_published', true)->orderBy('role')->get();
    }
}
