<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoSignal extends Model
{
    protected $fillable = ['room_id', 'from_user_id', 'to_user_id', 'type', 'payload', 'consumed'];
    protected $casts = ['consumed' => 'boolean'];

    public function room() { return $this->belongsTo(VideoRoom::class, 'room_id'); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
}
