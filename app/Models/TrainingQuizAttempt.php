<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingQuizAttempt extends Model
{
    protected $fillable = ['quiz_id', 'user_id', 'score', 'passed', 'answers'];
    protected $casts = ['passed' => 'boolean', 'answers' => 'array', 'score' => 'decimal:2'];

    public function quiz() { return $this->belongsTo(TrainingQuiz::class, 'quiz_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
