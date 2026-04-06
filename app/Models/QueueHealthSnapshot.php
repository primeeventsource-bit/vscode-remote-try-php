<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueHealthSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'queue', 'connection', 'pending_jobs', 'failed_jobs',
        'processed_last_5min', 'oldest_pending_seconds', 'state', 'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
