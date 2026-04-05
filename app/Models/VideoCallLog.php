<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoCallLog extends Model
{
    protected $fillable = ['room_id', 'user_id', 'event_type', 'meta'];
    protected $casts = ['meta' => 'array'];

    public function room() { return $this->belongsTo(VideoRoom::class, 'room_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
