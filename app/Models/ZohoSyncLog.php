<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoSyncLog extends Model
{
    use HasFactory;

    protected $table = 'zoho_sync_logs';

    protected $fillable = [
        'sync_type',
        'status',
        'records_synced',
        'records_created',
        'records_updated',
        'records_failed',
        'error_message',
        'started_at',
        'completed_at',
        'triggered_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
