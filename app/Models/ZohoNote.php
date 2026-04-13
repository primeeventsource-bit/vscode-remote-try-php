<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoNote extends Model
{
    use HasFactory;

    protected $table = 'zoho_notes';

    protected $fillable = [
        'zoho_id',
        'zoho_client_id',
        'note_content',
        'note_title',
        'created_by_name',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * The client this note belongs to.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ZohoClient::class, 'zoho_client_id');
    }
}
