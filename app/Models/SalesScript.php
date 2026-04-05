<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesScript extends Model
{
    protected $fillable = ['name', 'slug', 'category', 'stage', 'content', 'is_active', 'order_index', 'created_by'];
    protected $casts = ['is_active' => 'boolean'];

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeByStage($q, string $stage) { return $q->where('stage', $stage); }
    public function scopeByCategory($q, string $cat) { return $q->where('category', $cat); }
}
