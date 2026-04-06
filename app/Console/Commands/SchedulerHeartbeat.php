<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Records scheduler heartbeat every minute.
 * Used by the diagnostics dashboard to verify the scheduler is running.
 */
class SchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat';
    protected $description = 'Record a scheduler heartbeat for monitoring';

    public function handle(): int
    {
        try {
            DB::table('scheduler_heartbeats')->insert([
                'command' => 'scheduler:heartbeat',
                'status' => 'success',
                'duration_ms' => 0,
                'output' => null,
                'ran_at' => now(),
            ]);

            $this->info('Heartbeat recorded at ' . now());
        } catch (\Throwable $e) {
            $this->error('Heartbeat failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
