<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadDuplicateService;
use Illuminate\Console\Command;

class ScanLeadDuplicates extends Command
{
    protected $signature = 'leads:scan-duplicates {--chunk=500 : Chunk size for processing} {--limit=0 : Max leads to scan (0 = all)}';
    protected $description = 'Scan existing leads for duplicates and populate the lead_duplicates table';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $limit = (int) $this->option('limit');

        $query = Lead::query()->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $limit > 0 ? min($limit, Lead::count()) : Lead::count();
        $this->info("Scanning {$total} leads for duplicates (chunk size: {$chunkSize})...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $found = 0;
        $scanned = 0;

        $query->chunk($chunkSize, function ($leads) use (&$found, &$scanned, $bar, $limit) {
            foreach ($leads as $lead) {
                if ($limit > 0 && $scanned >= $limit) break;

                $row = $lead->only(['owner_name', 'phone1', 'phone2', 'email', 'resort', 'city', 'st']);
                $duplicates = LeadDuplicateService::findDuplicatesForRow($row, $lead->id);

                foreach ($duplicates as $dup) {
                    LeadDuplicateService::recordDuplicate(
                        $lead->id,
                        $dup['lead_id'],
                        $dup['duplicate_type'],
                        $dup['duplicate_reason'],
                        $dup['matched_fields']
                    );
                    $found++;
                }

                $scanned++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Scan complete. Found {$found} duplicate pairs across {$scanned} leads.");

        return 0;
    }
}
