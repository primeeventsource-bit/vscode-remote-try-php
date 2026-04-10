<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasParseLog extends Model
{
    protected $fillable = [
        'user_id', 'parse_type', 'county', 'state',
        'leads_found', 'leads_imported', 'leads_traced',
        'files_processed', 'cost_estimate', 'raw_input_preview',
    ];

    protected $casts = [
        'cost_estimate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
