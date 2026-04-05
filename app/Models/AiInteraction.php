<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    protected $fillable = ['user_id', 'lead_id', 'deal_id', 'type', 'input_text', 'context_json', 'output_text', 'output_json', 'model_used', 'prompt_template_id', 'confidence_score', 'response_time_ms', 'status', 'error_message'];
    protected $casts = ['context_json' => 'array', 'output_json' => 'array', 'confidence_score' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }
    public function feedback() { return $this->hasMany(AiFeedback::class, 'interaction_id'); }
}
