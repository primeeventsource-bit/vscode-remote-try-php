<?php

namespace App\Jobs;

use App\Models\SchedulerRunLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight probe job dispatched by the self-healing system
 * to verify the queue is actually processing jobs.
 *
 * Dispatch → process → record success = queue is working.
 * If this job never processes, the health check detects a stuck queue.
 */
class QueueHealthProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;
    public float $dispatchedAt;

    public function __construct()
    {
        $this->dispatchedAt = microtime(true);
    }

    public function handle(): void
    {
        $roundTripMs = (int) ((microtime(true) - $this->dispatchedAt) * 1000);

        SchedulerRunLog::record(
            'queue:probe',
            'success',
            $roundTripMs,
            "Queue probe completed in {$roundTripMs}ms"
        );
    }
}
