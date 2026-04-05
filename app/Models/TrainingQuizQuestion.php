<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingQuizQuestion extends Model
{
    protected $fillable = ['quiz_id', 'question', 'options', 'correct_answer', 'explanation', 'order_index'];
    protected $casts = ['options' => 'array'];

    public function quiz() { return $this->belongsTo(TrainingQuiz::class, 'quiz_id'); }
}
