<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoClientNote extends Model
{
    use HasFactory;

    protected $table = 'zoho_client_notes';

    protected $fillable = [
        'zoho_client_id',
        'user_id',
        'body',
    ];

    /**
     * The client this note belongs to.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ZohoClient::class, 'zoho_client_id');
    }

    /**
     * The user who created this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
