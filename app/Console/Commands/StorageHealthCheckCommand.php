<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageHealthService;
use App\Models\StorageStatus;
use Illuminate\Console\Command;

class StorageHealthCheckCommand extends Command
{
    protected $signature = 'storage:health-check';
    protected $description = 'Run storage health diagnostics and trigger failover if needed';

    public function handle(): int
    {
        $this->info('Running storage health check...');

        $results = StorageHealthService::runFullCheck();

        if (isset($results['skipped'])) {
            $this->warn('Storage resilience is disabled.');
            return 0;
        }

        $primaryOk  = $results['primary']['healthy'] ?? false;
        $fallbackOk = $results['fallback']['healthy'] ?? false;

        $this->line('  Primary  (' . ($results['primary']['disk'] ?? '?') . '): ' .
            ($primaryOk ? '<fg=green>HEALTHY</>' : '<fg=red>FAILED</>') .
            ' [' . ($results['primary']['latency_ms'] ?? '?') . 'ms]');

        $this->line('  Fallback (' . ($results['fallback']['disk'] ?? '?') . '): ' .
            ($fallbackOk ? '<fg=green>HEALTHY</>' : '<fg=red>FAILED</>') .
            ' [' . ($results['fallback']['latency_ms'] ?? '?') . 'ms]');

        $this->line('  Active disk: <fg=cyan>' . ($results['active'] ?? '?') . '</>');
        $this->line('  State: <fg=yellow>' . ($results['state'] ?? '?') . '</>');

        if (! $primaryOk && ! $fallbackOk) {
            $this->error('BOTH DISKS UNHEALTHY — storage operations may fail.');
            return 1;
        }

        if (($results['state'] ?? '') === 'failover_active') {
            $this->warn('Failover is active — using fallback disk.');
        }

        return 0;
    }
}
