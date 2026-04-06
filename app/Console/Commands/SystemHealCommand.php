<?php

namespace App\Console\Commands;

use App\Services\SelfHealingOrchestrator;
use Illuminate\Console\Command;

class SystemHealCommand extends Command
{
    protected $signature = 'system:heal {--subsystem= : Run only queue, scheduler, or storage}';
    protected $description = 'Run the unified self-healing cycle across queue, scheduler, and storage';

    public function handle(): int
    {
        $sub = $this->option('subsystem');

        if ($sub) {
            $this->info("Running self-heal for: {$sub}");
            $result = match ($sub) {
                'queue'     => SelfHealingOrchestrator::healQueue(),
                'scheduler' => SelfHealingOrchestrator::healScheduler(),
                'storage'   => SelfHealingOrchestrator::healStorage(),
                default     => ['error' => "Unknown subsystem: {$sub}"],
            };
            $this->printResult($sub, $result);
        } else {
            $this->info('Running full self-healing cycle...');
            $results = SelfHealingOrchestrator::run();

            foreach (['queue', 'scheduler', 'storage'] as $s) {
                $this->printResult($s, $results[$s] ?? []);
            }
        }

        return 0;
    }

    private function printResult(string $subsystem, array $result): void
    {
        $state = $result['state'] ?? 'unknown';
        $icon = match ($state) {
            'healthy'  => '<fg=green>HEALTHY</>',
            'degraded', 'lagging' => '<fg=yellow>DEGRADED</>',
            'stuck'    => '<fg=yellow>STUCK</>',
            'critical', 'failed' => '<fg=red>CRITICAL</>',
            'failover_active' => '<fg=blue>FAILOVER</>',
            default    => '<fg=gray>UNKNOWN</>',
        };

        $this->line("  [{$icon}] " . strtoupper($subsystem));

        // Show key details
        if (isset($result['pending'])) $this->line("       Pending jobs: {$result['pending']}");
        if (isset($result['failed'])) $this->line("       Failed jobs: {$result['failed']}");
        if (isset($result['minutes_ago'])) $this->line("       Last run: {$result['minutes_ago']} min ago");
        if (isset($result['active'])) $this->line("       Active disk: {$result['active']}");
        if (isset($result['error'])) $this->line("       <fg=red>Error: {$result['error']}</>");

        $actions = $result['actions'] ?? [];
        foreach ($actions as $a) {
            if (is_array($a)) {
                $s = $a['success'] ?? ($a['skipped'] ?? false);
                $this->line("       Action: " . ($s ? '<fg=green>OK</>' : '<fg=red>FAIL</>') . ' ' . json_encode($a));
            }
        }
    }
}
