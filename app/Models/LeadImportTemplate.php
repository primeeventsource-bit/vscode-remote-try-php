<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadImportTemplate extends Model
{
    protected $table = 'lead_import_templates';

    protected $fillable = [
        'header_hash', 'headers', 'mapping', 'confirmed_by', 'use_count', 'last_used_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'mapping' => 'array',
        'last_used_at' => 'datetime',
    ];

    public static function hashHeaders(array $normalizedHeaders): string
    {
        return sha1(implode('|', $normalizedHeaders));
    }
}
