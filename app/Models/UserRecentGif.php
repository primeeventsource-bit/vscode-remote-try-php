<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRecentGif extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gif_external_id',
        'gif_provider',
        'gif_url',
        'gif_preview_url',
        'gif_title',
        'used_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}
