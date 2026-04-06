<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerRunLog extends Model
{
    public $timestamps = false;
    protected $table = 'scheduler_run_log';

    protected $fillable = [
        'command', 'status', 'duration_ms', 'output', 'error',
        'expected_at', 'ran_at',
    ];

    protected $casts = [
        'expected_at' => 'datetime',
        'ran_at'      => 'datetime',
    ];

    public static function record(string $command, string $status, ?int $durationMs = null, ?string $output = null, ?string $error = null): void
    {
        try {
            static::create([
                'command'     => $command,
                'status'      => $status,
                'duration_ms' => $durationMs,
                'output'      => $output ? substr($output, 0, 2000) : null,
                'error'       => $error ? substr($error, 0, 2000) : null,
                'ran_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Table may not exist yet
        }
    }
}
