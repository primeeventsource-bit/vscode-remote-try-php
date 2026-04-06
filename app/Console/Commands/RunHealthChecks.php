<?php

namespace App\Console\Commands;

use App\Services\Monitor\HealthCheckRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunHealthChecks extends Command
{
    protected $signature = 'monitor:health';
    protected $description = 'Run all system health checks and record results';

    public function handle(): int
    {
        $this->info('Running health checks...');

        $results = HealthCheckRunner::runAll();

        // Record scheduler heartbeat
        try {
            if (Schema::hasTable('scheduler_heartbeats')) {
                DB::table('scheduler_heartbeats')->insert([
                    'command'     => 'monitor:health',
                    'status'      => 'success',
                    'duration_ms' => 0,
                    'ran_at'      => now(),
                ]);
            }
        } catch (\Throwable $e) {}

        foreach ($results as $component => $result) {
            $icon = match ($result['status']) {
                'healthy'  => '✓',
                'degraded' => '!',
                default    => '✕',
            };
            $this->line("  [{$icon}] {$component}: {$result['status']}");
        }

        $critical = collect($results)->where('status', 'critical')->count();
        if ($critical > 0) {
            $this->error("{$critical} critical issue(s) detected.");
            return 1;
        }

        $this->info('All checks passed.');
        return 0;
    }
}
