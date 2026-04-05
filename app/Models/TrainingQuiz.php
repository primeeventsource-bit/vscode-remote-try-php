<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingQuiz extends Model
{
    protected $fillable = ['module_id', 'title', 'passing_score', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function module() { return $this->belongsTo(TrainingModule::class, 'module_id'); }
    public function questions() { return $this->hasMany(TrainingQuizQuestion::class, 'quiz_id')->orderBy('order_index'); }
    public function attempts() { return $this->hasMany(TrainingQuizAttempt::class, 'quiz_id'); }
}
