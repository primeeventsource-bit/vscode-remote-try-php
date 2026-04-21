<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadImportBatch;
use App\Models\LeadImportFailure;
use App\Services\LeadDuplicateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLeadImportChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public int $batchId,
        public array $rows,
        public int $startRowNumber,
        public bool $isLastChunk = false,
    ) {}

    public function handle(): void
    {
        $batch = LeadImportBatch::find($this->batchId);
        if (!$batch || $batch->status === 'cancelled') return;

        if ($batch->status === 'pending') {
            $batch->update(['status' => 'processing', 'started_at' => now()]);
        }

        $inserted = [];
        $duplicateCount = 0;
        $invalidCount = 0;
        $failedCount = 0;
        $now = now()->toDateTimeString();
        $strategy = $batch->duplicate_strategy;

        foreach ($this->rows as $index => $row) {
            $rowNum = $this->startRowNumber + $index;

            try {
                // Validate required fields
                $ownerName = trim($row['owner_name'] ?? '');
                $resort = trim($row['resort'] ?? '');

                if ($ownerName === '' && $resort === '') {
                    LeadImportFailure::create([
                        'lead_import_batch_id' => $this->batchId,
                        'row_number' => $rowNum,
                        'raw_row' => $row,
                        'reason' => 'Missing owner name and resort',
                        'failure_type' => 'validation',
                    ]);
                    $invalidCount++;
                    continue;
                }

                // Check for duplicates
                $duplicates = LeadDuplicateService::findDuplicatesForRow($row);

                if (!empty($duplicates)) {
                    $bestMatch = $duplicates[0];

                    if ($strategy === 'skip') {
                        LeadImportFailure::create([
                            'lead_import_batch_id' => $this->batchId,
                            'row_number' => $rowNum,
                            'raw_row' => $row,
                            'reason' => 'Duplicate: ' . $bestMatch['duplicate_reason'],
                            'failure_type' => 'duplicate',
                            'matched_lead_id' => $bestMatch['lead_id'],
                            'duplicate_type' => $bestMatch['duplicate_type'],
                            'duplicate_reason' => $bestMatch['duplicate_reason'],
                            'matched_fields' => $bestMatch['matched_fields'],
                            'resolution_status' => 'skipped',
                        ]);
                        $duplicateCount++;
                        continue;
                    }

                    if ($strategy === 'flag') {
                        // Import the lead but flag the duplicate
                        LeadImportFailure::create([
                            'lead_import_batch_id' => $this->batchId,
                            'row_number' => $rowNum,
                            'raw_row' => $row,
                            'reason' => 'Possible duplicate: ' . $bestMatch['duplicate_reason'],
                            'failure_type' => 'duplicate',
                            'matched_lead_id' => $bestMatch['lead_id'],
                            'duplicate_type' => $bestMatch['duplicate_type'],
                            'duplicate_reason' => $bestMatch['duplicate_reason'],
                            'matched_fields' => $bestMatch['matched_fields'],
                            'resolution_status' => 'pending',
                        ]);
                        $duplicateCount++;
                        // Still import — fall through to insert below
                    }
                    // strategy === 'import_all' — just import, no logging
                }

                $inserted[] = [
                    'resort' => $resort,
                    'owner_name' => $ownerName,
                    'owner_name_2' => trim($row['owner_name_2'] ?? '') ?: null,
                    'phone1' => trim($row['phone1'] ?? ''),
                    'phone2' => trim($row['phone2'] ?? ''),
                    'city' => trim($row['city'] ?? ''),
                    'st' => trim($row['st'] ?? ''),
                    'zip' => trim($row['zip'] ?? ''),
                    'resort_location' => trim($row['resort_location'] ?? ''),
                    'email' => trim($row['email'] ?? '') ?: null,
                    'description' => trim($row['description'] ?? '') ?: null,
                    'source' => 'csv',
                    'imported_at' => $now,
                    'import_batch_id' => $this->batchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Batch insert every 500 rows
                if (count($inserted) >= 500) {
                    $failedCount += $this->safeBulkInsert($inserted, $strategy);
                    $inserted = [];
                }
            } catch (\Throwable $e) {
                LeadImportFailure::create([
                    'lead_import_batch_id' => $this->batchId,
                    'row_number' => $rowNum,
                    'raw_row' => $row,
                    'reason' => 'Exception: ' . mb_substr($e->getMessage(), 0, 2000),
                    'failure_type' => 'exception',
                ]);
                $failedCount++;
            }
        }

        // Insert remaining
        if (!empty($inserted)) {
            $failedCount += $this->safeBulkInsert($inserted, $strategy);
        }

        $successCount = count($this->rows) - $duplicateCount - $invalidCount - $failedCount;
        if ($strategy === 'flag') {
            // Flagged duplicates were still imported
            $successCount += $duplicateCount;
        }

        // Update batch progress atomically
        DB::table('lead_import_batches')
            ->where('id', $this->batchId)
            ->update([
                'processed_rows' => DB::raw("processed_rows + " . count($this->rows)),
                'successful_rows' => DB::raw("successful_rows + {$successCount}"),
                'duplicate_rows' => DB::raw("duplicate_rows + {$duplicateCount}"),
                'invalid_rows' => DB::raw("invalid_rows + {$invalidCount}"),
                'failed_rows' => DB::raw("failed_rows + {$failedCount}"),
            ]);

        // Finalize if last chunk
        if ($this->isLastChunk) {
            $batch->refresh();

            // Wait briefly for other chunks to finish
            $attempts = 0;
            while ($batch->processed_rows < $batch->total_rows && $attempts < 30) {
                sleep(1);
                $batch->refresh();
                $attempts++;
            }

            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'summary_json' => [
                    'total' => $batch->total_rows,
                    'imported' => $batch->successful_rows,
                    'duplicates' => $batch->duplicate_rows,
                    'invalid' => $batch->invalid_rows,
                    'failed' => $batch->failed_rows,
                ],
            ]);
        }
    }

    private function recordDuplicatesForInserted(array $rows): void
    {
        // Get the IDs of recently inserted leads matching this batch
        $recentLeads = Lead::where('import_batch_id', $this->batchId)
            ->orderByDesc('id')
            ->limit(count($rows))
            ->get();

        foreach ($recentLeads as $lead) {
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
            }
        }
    }

    /**
     * Bulk insert with graceful fallback. If the whole chunk fails (e.g. a
     * single row has a too-long string or some other constraint violation),
     * retry row-by-row so surviving rows still land and we can log the
     * specific bad row to lead_import_failures. Returns the number of rows
     * that failed so the caller can track them.
     */
    private function safeBulkInsert(array $rows, string $strategy): int
    {
        if (empty($rows)) return 0;

        try {
            Lead::insert($rows);
            if ($strategy === 'flag' || $strategy === 'import_all') {
                try {
                    $this->recordDuplicatesForInserted($rows);
                } catch (\Throwable $dupErr) {
                    // Duplicate bookkeeping failing must not undo the insert.
                    Log::warning('recordDuplicatesForInserted threw after successful insert', [
                        'batch_id' => $this->batchId,
                        'error'    => mb_substr($dupErr->getMessage(), 0, 500),
                    ]);
                }
            }
            return 0;
        } catch (\Throwable $bulkError) {
            Log::warning('Bulk lead insert failed, falling back to per-row', [
                'batch_id' => $this->batchId,
                'rows'     => count($rows),
                'error'    => mb_substr($bulkError->getMessage(), 0, 500),
            ]);
        }

        $failed = 0;
        $succeeded = [];
        foreach ($rows as $row) {
            try {
                Lead::insert([$row]);
                $succeeded[] = $row;
            } catch (\Throwable $rowError) {
                LeadImportFailure::create([
                    'lead_import_batch_id' => $this->batchId,
                    'row_number' => 0, // row_number is per-chunk; at fallback time we don't know original index
                    'raw_row' => $row,
                    'reason' => 'Insert failed: ' . mb_substr($rowError->getMessage(), 0, 2000),
                    'failure_type' => 'exception',
                ]);
                $failed++;
            }
        }

        if (!empty($succeeded) && ($strategy === 'flag' || $strategy === 'import_all')) {
            try {
                $this->recordDuplicatesForInserted($succeeded);
            } catch (\Throwable $dupErr) {
                Log::warning('recordDuplicatesForInserted threw after fallback insert', [
                    'batch_id' => $this->batchId,
                    'error'    => mb_substr($dupErr->getMessage(), 0, 500),
                ]);
            }
        }

        return $failed;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Lead import chunk failed', [
            'batch_id' => $this->batchId,
            'start_row' => $this->startRowNumber,
            'error' => $exception->getMessage(),
        ]);

        $batch = LeadImportBatch::find($this->batchId);
        if ($batch && $this->isLastChunk) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Chunk processing failed: ' . substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
}
