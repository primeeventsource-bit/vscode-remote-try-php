<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type', 'disk', 'previous_disk', 'new_disk',
        'severity', 'message', 'context', 'created_at',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public static function log(string $type, string $message, string $severity = 'info', ?string $disk = null, ?array $context = null): void
    {
        try {
            static::create([
                'event_type' => $type,
                'disk'       => $disk,
                'severity'   => $severity,
                'message'    => substr($message, 0, 500),
                'context'    => $context,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Table may not exist yet
        }
    }
}
