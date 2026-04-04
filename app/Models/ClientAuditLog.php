<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_role',
        'deal_id',
        'action',
        'section',
        'changed_fields',
        'before_values',
        'after_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'before_values' => 'array',
        'after_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
