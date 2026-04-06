<?php

namespace App\Console\Commands;

use App\Models\StorageEvent;
use App\Models\StorageStatus;
use Illuminate\Console\Command;

class StorageForceCommand extends Command
{
    protected $signature = 'storage:force {mode : primary|fallback|auto}';
    protected $description = 'Force storage to a specific disk or return to auto mode';

    public function handle(): int
    {
        $mode = $this->argument('mode');

        $status = StorageStatus::current();

        if ($mode === 'auto') {
            $old = $status->forced_disk;
            $status->forced_disk = null;
            $status->save();
            StorageEvent::log('forced_auto', "Returned to auto mode (was: {$old})", 'info');
            $this->info('Storage returned to auto mode.');
        } elseif ($mode === 'primary') {
            $status->forced_disk = $status->primary_disk;
            $status->active_disk = $status->primary_disk;
            $status->save();
            StorageEvent::log('forced_primary', "Forced to primary: {$status->primary_disk}", 'warning');
            $this->info("Storage forced to primary disk: {$status->primary_disk}");
        } elseif ($mode === 'fallback') {
            $status->forced_disk = $status->fallback_disk;
            $status->active_disk = $status->fallback_disk;
            $status->save();
            StorageEvent::log('forced_fallback', "Forced to fallback: {$status->fallback_disk}", 'warning');
            $this->info("Storage forced to fallback disk: {$status->fallback_disk}");
        } else {
            $this->error('Invalid mode. Use: primary, fallback, or auto');
            return 1;
        }

        return 0;
    }
}
