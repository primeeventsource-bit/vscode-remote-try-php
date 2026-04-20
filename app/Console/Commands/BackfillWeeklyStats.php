<?php

namespace App\Console\Commands;

use App\Services\WeeklyStatsService;
use Illuminate\Console\Command;

class BackfillWeeklyStats extends Command
{
    protected $signature = 'stats:backfill {--week= : Specific ISO week key e.g. 2026-W10}';

    protected $description = 'Backfill weekly stats snapshots from existing deals data';

    public function handle(WeeklyStatsService $service): int
    {
        if ($week = $this->option('week')) {
            $this->info("Snapshotting week: {$week}");
            $service->snapshotWeek($week);
            $this->info('Done.');
            return self::SUCCESS;
        }

        $this->info('Backfilling ALL weeks from oldest deal...');
        $count = $service->backfillAllWeeks(function (string $key) {
            $this->line("  • {$key}");
        });
        $this->info("Backfill complete — {$count} weeks snapshotted.");
        return self::SUCCESS;
    }
}
