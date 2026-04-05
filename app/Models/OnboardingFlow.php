<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingFlow extends Model
{
    protected $fillable = ['role', 'name', 'description', 'is_active', 'version'];
    protected $casts = ['is_active' => 'boolean'];

    public function steps() { return $this->hasMany(OnboardingStep::class, 'flow_id')->orderBy('sort_order'); }
    public function progress() { return $this->hasMany(UserOnboardingProgress::class, 'flow_id'); }

    public static function forRole(string $role): ?self
    {
        return self::where('role', $role)->where('is_active', true)->first();
    }
}
