<?php

namespace App\Console\Commands;

use App\Services\Chat\CallService;
use Illuminate\Console\Command;

/**
 * Cleans up stale ringing calls that were never answered.
 */
class CleanupStaleCalls extends Command
{
    protected $signature = 'calls:cleanup {--seconds=60 : Seconds after which a ringing call is considered stale}';
    protected $description = 'Mark stale ringing calls as missed';

    public function handle(CallService $callService): int
    {
        $seconds = (int) $this->option('seconds');
        $count = $callService->cleanupStaleCalls($seconds);
        $this->info("Cleaned up {$count} stale call(s).");
        return self::SUCCESS;
    }
}
