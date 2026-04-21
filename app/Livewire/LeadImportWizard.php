<?php

namespace App\Livewire;

use App\Jobs\ProcessLeadImportChunk;
use App\Models\LeadImportBatch;
use App\Models\LeadImportTemplate;
use App\Services\LeadImportAiMapper;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('AI Lead Import')]
class LeadImportWizard extends Component
{
    use WithFileUploads;

    /** Step 1: upload */
    public $csvFile = null;
    public string $csvText = '';
    public string $duplicateStrategy = 'skip'; // skip | flag | import_all

    /** Step 2: preview / mapping */
    public string $step = 'upload'; // upload | preview | done
    public array $headers = [];             // raw headers from row 1
    public array $normalizedHeaders = [];   // same order, normalized
    public array $mapping = [];             // normalizedHeader => lead field or null
    public array $confidence = [];          // normalizedHeader => 'high'|'medium'|'low'|'none'
    public array $sampleRows = [];          // first 3 data rows
    public int $totalDataRows = 0;
    public string $tempPath = '';
    public string $originalFilename = 'pasted_csv';
    public bool $fromRememberedTemplate = false;

    /** Step 3: summary */
    public array $result = [];

    public string $error = '';

    /** Lead columns users can pick in the dropdown. */
    public const LEAD_FIELDS = [
        'resort', 'owner_name', 'owner_name_2', 'phone1', 'phone2',
        'email', 'city', 'st', 'zip', 'resort_location', 'description',
    ];

    public function mount(): void
    {
        $this->gate();
    }

    private function gate(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) {
            abort(403);
        }
    }

    public function analyze(): void
    {
        $this->gate();
        $this->error = '';
        $this->result = [];

        // Resolve source to a persistent temp file so we can re-read it at import time.
        // (WithFileUploads' temp file evaporates between Livewire requests; copy once.)
        if ($this->csvFile) {
            try {
                $this->validate(['csvFile' => 'file|max:102400|mimes:csv,txt']);
            } catch (\Throwable $e) {
                $this->error = 'Invalid file: ' . $e->getMessage();
                return;
            }
            $this->tempPath = tempnam(sys_get_temp_dir(), 'lwi_');
            copy($this->csvFile->getRealPath(), $this->tempPath);
            $this->originalFilename = $this->csvFile->getClientOriginalName();
        } elseif (trim($this->csvText) !== '') {
            $this->tempPath = tempnam(sys_get_temp_dir(), 'lwi_');
            file_put_contents($this->tempPath, preg_replace('/^\xEF\xBB\xBF/', '', $this->csvText));
            $this->originalFilename = 'pasted_csv';
        } else {
            $this->error = 'Upload a CSV file or paste CSV text.';
            return;
        }

        // Read row 1 + up to 3 data rows + a total count in one pass.
        $handle = @fopen($this->tempPath, 'r');
        if (!$handle) {
            $this->error = 'Could not open the uploaded file.';
            return;
        }

        try {
            $first = fgetcsv($handle);
            if ($first === false || empty($first)) {
                $this->error = 'The file is empty.';
                return;
            }
            // Strip BOM + trim
            if (isset($first[0])) {
                $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
            }
            $this->headers = array_map(fn($c) => trim((string) $c), $first);
            $this->normalizedHeaders = array_map(fn($h) => self::normalizeHeader($h), $this->headers);

            $this->sampleRows = [];
            while (($row = fgetcsv($handle)) !== false && count($this->sampleRows) < 3) {
                $this->sampleRows[] = array_map(fn($c) => trim((string) $c), $row);
            }
            // Cheap total estimate: continue reading to count non-empty rows.
            $total = count($this->sampleRows);
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty(array_filter($row, fn($c) => trim((string)$c) !== ''))) {
                    $total++;
                }
            }
            $this->totalDataRows = $total;
        } finally {
            fclose($handle);
        }

        $this->applyInitialMapping();
        $this->step = 'preview';
    }

    private function applyInitialMapping(): void
    {
        $hash = LeadImportTemplate::hashHeaders($this->normalizedHeaders);
        $template = LeadImportTemplate::where('header_hash', $hash)->first();

        if ($template) {
            $this->mapping = $template->mapping;
            $this->confidence = array_fill_keys($this->normalizedHeaders, 'high');
            $this->fromRememberedTemplate = true;
            return;
        }

        $this->fromRememberedTemplate = false;
        $synonyms = config('lead_import_mappings', []);
        $this->mapping = [];
        $this->confidence = [];

        // Stage A: deterministic synonym lookup
        $unmatched = [];
        foreach ($this->normalizedHeaders as $idx => $norm) {
            $matched = null;
            foreach ($synonyms as $field => $syns) {
                if (in_array($norm, $syns, true)) {
                    $matched = $field;
                    break;
                }
            }

            if ($matched === 'countystate') {
                // Special combined field handled at import time; no direct lead column
                $this->mapping[$norm] = '__countystate__';
                $this->confidence[$norm] = 'high';
            } elseif ($matched) {
                $this->mapping[$norm] = $matched;
                $this->confidence[$norm] = 'high';
            } else {
                $this->mapping[$norm] = null;
                $this->confidence[$norm] = 'none';
                $unmatched[] = $idx;
            }
        }

        // Stage B: AI fallback for unmatched headers only
        if (!empty($unmatched)) {
            $mapper = app(LeadImportAiMapper::class);
            foreach ($unmatched as $idx) {
                $raw = $this->headers[$idx] ?? '';
                $norm = $this->normalizedHeaders[$idx] ?? '';
                $samples = [];
                foreach ($this->sampleRows as $row) {
                    if (isset($row[$idx])) $samples[] = $row[$idx];
                }
                $ai = $mapper->map($raw, $samples);
                if ($ai['field'] && in_array($ai['field'], self::LEAD_FIELDS, true)) {
                    $this->mapping[$norm] = $ai['field'];
                    $this->confidence[$norm] = $ai['confidence'] === 'high' ? 'medium' : 'low';
                }
            }
        }
    }

    public function cancel(): void
    {
        $this->cleanupTemp();
        $this->reset(['headers', 'normalizedHeaders', 'mapping', 'confidence', 'sampleRows',
            'totalDataRows', 'tempPath', 'csvFile', 'csvText', 'result', 'error',
            'originalFilename', 'fromRememberedTemplate']);
        $this->step = 'upload';
    }

    public function import(): void
    {
        $this->gate();
        if ($this->step !== 'preview' || !$this->tempPath || !file_exists($this->tempPath)) {
            $this->error = 'Upload session expired. Start over.';
            $this->step = 'upload';
            return;
        }

        // Build an index-ordered mapping: column index → lead field (or special/null)
        $colMap = [];
        foreach ($this->normalizedHeaders as $idx => $norm) {
            $colMap[$idx] = $this->mapping[$norm] ?? null;
        }

        // Require at least owner_name OR resort OR phone1 mapped, else the import is pointless.
        $mappedFields = array_filter(array_values($colMap));
        if (!array_intersect(['owner_name', 'resort', 'phone1'], $mappedFields)) {
            $this->error = 'Map at least one of: Owner Name, Resort, or Phone1 — otherwise nothing meaningful will import.';
            return;
        }

        // Persist the mapping as a reusable template (upsert by header hash)
        $this->rememberTemplate();

        // Build the batch + stream rows → dispatch in 500-chunks
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $batch = LeadImportBatch::create([
            'user_id' => auth()->id(),
            'original_filename' => $this->originalFilename,
            'file_type' => 'csv',
            'total_rows' => 0,
            'status' => 'pending',
            'duplicate_strategy' => $this->duplicateStrategy,
        ]);

        $handle = @fopen($this->tempPath, 'r');
        if (!$handle) {
            $batch->delete();
            $this->error = 'Could not open the file for import.';
            return;
        }

        try {
            fgetcsv($handle); // skip header row

            $buffer = [];
            $pending = null;
            $rowOffset = 1;
            $chunkCount = 0;
            $total = 0;
            $invalidLocal = 0;

            while (($v = fgetcsv($handle)) !== false) {
                $v = array_map(fn($x) => trim((string) $x), $v);
                if (!array_filter($v, fn($c) => $c !== '')) continue; // skip fully-empty rows
                $mapped = $this->mapRowByColMap($v, $colMap);
                if ($mapped === null) { $invalidLocal++; continue; }
                $buffer[] = $mapped;
                $total++;

                if (count($buffer) >= 500) {
                    if ($pending !== null) {
                        ProcessLeadImportChunk::dispatch($batch->id, $pending['rows'], $pending['offset'], false);
                        $chunkCount++;
                    }
                    $pending = ['rows' => $buffer, 'offset' => $rowOffset];
                    $rowOffset += count($buffer);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                if ($pending !== null) {
                    ProcessLeadImportChunk::dispatch($batch->id, $pending['rows'], $pending['offset'], false);
                    $chunkCount++;
                }
                $pending = ['rows' => $buffer, 'offset' => $rowOffset];
            }

            if ($pending === null) {
                $batch->delete();
                $this->error = 'No importable rows found.';
                return;
            }

            ProcessLeadImportChunk::dispatch($batch->id, $pending['rows'], $pending['offset'], true);
            $chunkCount++;

            $batch->update(['total_rows' => $total]);

            $this->result = [
                'batch_id' => $batch->id,
                'total' => $total,
                'chunks' => $chunkCount,
                'strategy' => $this->duplicateStrategy,
                'skipped_empty' => $invalidLocal,
            ];
            Log::info('AI lead import queued', ['user' => auth()->id(), 'batch' => $batch->id, 'rows' => $total]);

            $this->cleanupTemp();
            $this->step = 'done';
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
    }

    private function rememberTemplate(): void
    {
        $hash = LeadImportTemplate::hashHeaders($this->normalizedHeaders);
        try {
            LeadImportTemplate::updateOrCreate(
                ['header_hash' => $hash],
                [
                    'headers' => $this->headers,
                    'mapping' => $this->mapping,
                    'confirmed_by' => auth()->id(),
                    'last_used_at' => now(),
                    'use_count' => \App\Models\LeadImportTemplate::where('header_hash', $hash)->value('use_count') + 1 ?: 1,
                ]
            );
        } catch (\Throwable $e) {
            // Template-remember failure must not block the import
            Log::warning('LeadImportWizard rememberTemplate failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Apply colMap to a data row, returning the lead-shaped array or null
     * if the row has no useful data (no owner_name, resort, or phone).
     */
    private function mapRowByColMap(array $row, array $colMap): ?array
    {
        $out = [
            'resort' => '', 'owner_name' => '', 'owner_name_2' => '', 'phone1' => '', 'phone2' => '',
            'city' => '', 'st' => '', 'zip' => '', 'resort_location' => '', 'email' => '', 'description' => '',
        ];

        foreach ($colMap as $idx => $field) {
            if (!$field) continue;
            $val = (string) ($row[$idx] ?? '');

            if ($field === '__countystate__') {
                // "ORANGE, FL" → city + st (only if not already set)
                $parts = array_map('trim', explode(',', $val, 2));
                if ($out['city'] === '' && isset($parts[0])) $out['city'] = $parts[0];
                if ($out['st'] === '' && isset($parts[1])) $out['st'] = strtoupper($parts[1]);
                continue;
            }

            if (in_array($field, self::LEAD_FIELDS, true)) {
                $out[$field] = $val;
            }
        }

        if ($out['owner_name'] === '' && $out['resort'] === '' && $out['phone1'] === '') {
            return null;
        }
        return $out;
    }

    private function cleanupTemp(): void
    {
        if ($this->tempPath && file_exists($this->tempPath)) {
            @unlink($this->tempPath);
        }
        $this->tempPath = '';
    }

    public static function normalizeHeader(string $h): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $h));
    }

    public function render()
    {
        return view('livewire.lead-import-wizard', [
            'leadFields' => self::LEAD_FIELDS,
        ]);
    }
}
