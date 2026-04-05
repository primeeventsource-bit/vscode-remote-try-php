<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingModule extends Model
{
    protected $fillable = ['title', 'slug', 'category', 'description', 'content', 'order_index', 'is_active', 'is_required', 'estimated_minutes', 'created_by'];
    protected $casts = ['is_active' => 'boolean', 'is_required' => 'boolean'];

    public function quizzes() { return $this->hasMany(TrainingQuiz::class, 'module_id'); }
    public function progress() { return $this->hasMany(TrainingProgress::class, 'module_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('order_index'); }
}
