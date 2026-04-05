<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProgress extends Model
{
    protected $table = 'training_progress';
    protected $fillable = ['user_id', 'module_id', 'status', 'score', 'started_at', 'completed_at'];
    protected $casts = ['score' => 'decimal:2', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function module() { return $this->belongsTo(TrainingModule::class, 'module_id'); }
}
