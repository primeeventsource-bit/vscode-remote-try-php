<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'body', 'channel', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeForChannel($q, string $ch) { return $q->where('channel', $ch); }
}
