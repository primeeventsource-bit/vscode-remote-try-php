<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_id', 'user_id', 'event_type', 'payload', 'created_at'];
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    public function meeting() { return $this->belongsTo(Meeting::class); }
    public function user() { return $this->belongsTo(User::class); }
}
