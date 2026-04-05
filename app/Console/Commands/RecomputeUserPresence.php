<?php

namespace App\Console\Commands;

use App\Services\Presence\UserPresenceService;
use Illuminate\Console\Command;

class RecomputeUserPresence extends Command
{
    protected $signature = 'presence:recompute';
    protected $description = 'Mark stale users as offline if heartbeat has stopped';

    public function handle(): int
    {
        $count = UserPresenceService::recomputeStaleUsers();
        if ($count > 0) {
            $this->info("Marked {$count} stale user(s) offline.");
        }
        return 0;
    }
}
