<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScriptVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'script_id', 'version_number', 'title_snapshot', 'body_snapshot',
        'content_hash', 'character_count', 'source_type', 'source_filename',
        'edited_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function script()
    {
        return $this->belongsTo(SalesScript::class, 'script_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
