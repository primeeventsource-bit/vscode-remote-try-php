<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadImportBatch;
use Illuminate\Console\Command;

class RecoverStuckLeadImports extends Command
{
    protected $signature = 'leads:recover-stuck-imports
        {--minutes=30 : Mark batches stuck in pending/processing longer than this as failed}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Auto-fail lead import batches stuck in pending/processing past the timeout';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($minutes);

        $stuck = LeadImportBatch::whereIn('status', ['pending', 'processing'])
            ->where(function ($q) use ($cutoff) {
                $q->where('updated_at', '<', $cutoff)
                  ->orWhere('created_at', '<', $cutoff);
            })
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck import batches found.');
            return self::SUCCESS;
        }

        foreach ($stuck as $batch) {
            $imported = Lead::where('import_batch_id', $batch->id)->count();
            $this->line("Batch #{$batch->id} ({$batch->original_filename}) — status={$batch->status}, total={$batch->total_rows}, imported={$imported}, age=".$batch->updated_at->diffForHumans());

            if ($dry) continue;

            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'successful_rows' => $imported,
                'processed_rows' => max($batch->processed_rows, $imported),
                'error_message' => "Auto-failed by recovery: stuck in {$batch->status} > {$minutes} min",
                'summary_json' => [
                    'total' => $batch->total_rows,
                    'imported' => $imported,
                    'duplicates' => $batch->duplicate_rows,
                    'invalid' => $batch->invalid_rows,
                    'failed' => max(0, $batch->total_rows - $imported - $batch->duplicate_rows - $batch->invalid_rows),
                    'auto_recovered' => true,
                ],
            ]);
        }

        $verb = $dry ? 'Would auto-fail' : 'Auto-failed';
        $this->info("{$verb} {$stuck->count()} stuck import batch(es).");
        return self::SUCCESS;
    }
}
