<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainerRecommendation extends Model
{
    protected $fillable = [
        'user_id', 'module', 'entity_type', 'entity_id',
        'recommendation_type', 'title', 'message',
        'action_label', 'action_target', 'status', 'dismissed_at',
    ];

    protected $casts = ['dismissed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeActive($q) { return $q->where('status', 'active'); }
}
