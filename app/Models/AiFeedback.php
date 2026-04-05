<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFeedback extends Model
{
    protected $table = 'ai_feedback';
    protected $fillable = ['interaction_id', 'user_id', 'feedback_type', 'notes'];

    public function interaction() { return $this->belongsTo(AiInteraction::class, 'interaction_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
