<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasParseLog extends Model
{
    protected $fillable = [
        'user_id', 'parse_type', 'county', 'state',
        'leads_found', 'leads_imported', 'files_processed',
        'raw_input_preview',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
