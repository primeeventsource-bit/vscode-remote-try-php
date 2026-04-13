<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoDeal extends Model
{
    use HasFactory;

    protected $table = 'zoho_deals';

    protected $fillable = [
        'zoho_id',
        'zoho_client_id',
        'deal_name',
        'amount',
        'stage',
        'pipeline',
        'closing_date',
        'deal_owner',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'closing_date' => 'date',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * The client this deal belongs to.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ZohoClient::class, 'zoho_client_id');
    }
}
