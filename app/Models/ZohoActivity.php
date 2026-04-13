<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoActivity extends Model
{
    use HasFactory;

    protected $table = 'zoho_activities';

    protected $fillable = [
        'zoho_id',
        'zoho_client_id',
        'activity_type',
        'subject',
        'description',
        'activity_date',
        'status',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * The client this activity belongs to.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ZohoClient::class, 'zoho_client_id');
    }
}
