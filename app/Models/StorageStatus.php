<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageStatus extends Model
{
    protected $fillable = [
        'primary_disk', 'fallback_disk', 'active_disk', 'state',
        'primary_healthy', 'fallback_healthy', 'failure_count', 'recovery_count',
        'primary_latency_ms', 'fallback_latency_ms',
        'last_checked_at', 'last_failover_at', 'last_recovery_at',
        'forced_disk', 'details',
    ];

    protected $casts = [
        'primary_healthy'  => 'boolean',
        'fallback_healthy' => 'boolean',
        'details'          => 'array',
        'last_checked_at'  => 'datetime',
        'last_failover_at' => 'datetime',
        'last_recovery_at' => 'datetime',
    ];

    /**
     * Get or create the singleton status row.
     */
    public static function current(): self
    {
        try {
            $status = static::first();
            if ($status) return $status;

            return static::create([
                'primary_disk'  => config('storage_resilience.primary_disk', 'public'),
                'fallback_disk' => config('storage_resilience.fallback_disk', 'local'),
                'active_disk'   => config('storage_resilience.primary_disk', 'public'),
                'state'         => 'healthy',
            ]);
        } catch (\Throwable $e) {
            // Table may not exist yet — return a transient object
            $s = new static;
            $s->primary_disk = config('storage_resilience.primary_disk', 'public');
            $s->fallback_disk = config('storage_resilience.fallback_disk', 'local');
            $s->active_disk = $s->primary_disk;
            $s->state = 'healthy';
            $s->primary_healthy = true;
            $s->fallback_healthy = true;
            return $s;
        }
    }
}
