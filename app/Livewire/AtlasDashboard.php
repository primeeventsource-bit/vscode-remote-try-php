<?php

namespace App\Livewire;

use App\Models\AtlasLead;
use App\Models\AtlasParseLog;
use App\Services\AtlasAIService;
use App\Services\TracerfyService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Atlas Global')]
class AtlasDashboard extends Component
{
    use WithFileUploads, WithPagination;

    public string $activeTab = 'dashboard';
    public string $successMessage = '';
    public string $errorMessage = '';

    // Sheets Upload
    public $csvFile;
    public string $csvPasteText = '';
    public ?array $csvParsed = null;
    public array $columnMap = ['name' => '', 'phone' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'resort' => ''];
    public bool $csvImported = false;

    // BatchData Skip Trace
    public bool $tracing = false;
    public ?array $traceResults = null;
    public string $traceError = '';
    public int $traceProgress = 0;
    public int $traceTotal = 0;

    // AI Parser
    public string $pasteText = '';
    public string $aiCounty = 'Orange';
    public string $aiState = 'FL';
    public bool $aiParsing = false;
    public ?array $aiResults = null;
    public string $aiError = '';
    public array $aiSelected = [];

    // PDF Upload
    public $pdfFiles = [];
    public $pdfQueue = [];
    public string $pdfCounty = 'Orange';
    public string $pdfState = 'FL';
    public bool $pdfParsing = false;
    public ?array $pdfResults = null;
    public string $pdfError = '';
    public array $pdfSelected = [];

    // Leads
    public string $searchQuery = '';
    public string $filterStatus = 'ALL';
    public bool $showAddForm = false;
    public string $formName = '';
    public string $formPhone = '';
    public string $formResort = '';
    public string $formAddress = '';
    public string $formCity = '';
    public string $formState = '';
    public string $formZip = '';

    // Settings
    public string $tracerfyKey = '';
    public string $anthropicKey = '';
    public bool $keyIsSaved = false;
    public bool $aiKeyIsSaved = false;

    // Tracerfy queue tracking
    public ?int $traceQueueId = null;
    public bool $traceQueuePending = false;

    // Counties
    public string $countySearch = '';

    // ─── CSV / Sheets Upload ────────────────────────────
    public function updatedCsvFile()
    {
        $this->parseCSVFile();
    }

    public function parseCSVPaste()
    {
        if (strlen(trim($this->csvPasteText)) < 10) {
            $this->errorMessage = 'Please paste spreadsheet data.';
            return;
        }
        $this->csvParsed = $this->parseCSVString($this->csvPasteText);
        $this->autoMapColumns();
    }

    protected function parseCSVFile()
    {
        if (!$this->csvFile) return;

        $this->validate(['csvFile' => 'file|max:51200']);
        $content = file_get_contents($this->csvFile->getRealPath());
        $this->csvParsed = $this->parseCSVString($content);
        $this->autoMapColumns();
    }

    protected function parseCSVString(string $content): array
    {
        $lines = preg_split('/\r?\n/', trim($content));
        if (count($lines) < 2) return ['headers' => [], 'rows' => []];

        // Detect delimiter
        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';

        $headers = str_getcsv(array_shift($lines), $delimiter);
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach (array_slice($lines, 0, 10000) as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line, $delimiter);
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function autoMapColumns()
    {
        if (!$this->csvParsed) return;

        $headers = $this->csvParsed['headers'];
        $map = ['name' => '', 'phone' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'resort' => ''];

        foreach ($headers as $h) {
            $lower = strtolower($h);
            if (!$map['name'] && (str_contains($lower, 'owner') || str_contains($lower, 'name') || str_contains($lower, 'grantee'))) $map['name'] = $h;
            elseif (!$map['phone'] && (str_contains($lower, 'phone') || str_contains($lower, 'tel') || str_contains($lower, 'mobile'))) $map['phone'] = $h;
            elseif (!$map['address'] && (str_contains($lower, 'address') || str_contains($lower, 'street'))) $map['address'] = $h;
            elseif (!$map['city'] && str_contains($lower, 'city')) $map['city'] = $h;
            elseif (!$map['state'] && str_contains($lower, 'state')) $map['state'] = $h;
            elseif (!$map['zip'] && (str_contains($lower, 'zip') || str_contains($lower, 'postal'))) $map['zip'] = $h;
            elseif (!$map['resort'] && (str_contains($lower, 'resort') || str_contains($lower, 'grantor') || str_contains($lower, 'seller'))) $map['resort'] = $h;
        }

        $this->columnMap = $map;
    }

    public function importCSV()
    {
        if (!$this->csvParsed || empty($this->csvParsed['rows'])) return;

        $imported = 0;
        foreach ($this->csvParsed['rows'] as $row) {
            $name = $row[$this->columnMap['name']] ?? '';
            if (!$name) continue;

            AtlasLead::create([
                'grantee'        => $name,
                'grantor'        => $row[$this->columnMap['resort']] ?? '',
                'existing_phone' => $row[$this->columnMap['phone']] ?? '',
                'address'        => $row[$this->columnMap['address']] ?? '',
                'city'           => $row[$this->columnMap['city']] ?? '',
                'state'          => $row[$this->columnMap['state']] ?? '',
                'zip'            => $row[$this->columnMap['zip']] ?? '',
                'status'         => 'new',
                'source'         => 'sheets',
                'source_filename' => $this->csvFile?->getClientOriginalName(),
                'created_by'     => auth()->id(),
            ]);
            $imported++;
        }

        AtlasParseLog::create([
            'user_id' => auth()->id(),
            'parse_type' => 'sheets',
            'leads_found' => $imported,
            'leads_imported' => $imported,
        ]);

        $this->csvImported = true;
        $this->successMessage = "{$imported} leads imported from spreadsheet.";
    }

    // ─── Tracerfy Skip Trace ───────────────────────────
    public function runSkipTrace()
    {
        $service = app(TracerfyService::class);

        if (!$service->isConfigured()) {
            $this->traceError = 'Tracerfy API key not set. Go to Settings tab.';
            return;
        }

        $leads = AtlasLead::whereIn('status', ['new', 'searched'])->get();
        if ($leads->isEmpty()) {
            $this->traceError = 'No leads to trace.';
            return;
        }

        // Check how many leads have addresses
        $withAddress = $leads->filter(fn($l) => !empty(trim($l->address ?? '')))->count();
        $withoutAddress = $leads->count() - $withAddress;

        if ($withAddress === 0) {
            $this->traceError = "None of your {$leads->count()} leads have addresses. Tracerfy requires address + name to skip trace. Make sure your CSV has address/city/state columns and map them during import.";
            return;
        }

        if ($withoutAddress > 0) {
            $this->successMessage = "Tracing {$withAddress} leads with addresses. {$withoutAddress} leads skipped (no address).";
        }

        // Only send leads that have addresses
        $traceable = $leads->filter(fn($l) => !empty(trim($l->address ?? '')));

        $this->tracing = true;
        $this->traceTotal = $traceable->count();
        $this->traceProgress = 0;
        $this->traceResults = [];
        $this->traceError = '';

        // Build lead data for Tracerfy CSV upload
        $requests = $traceable->map(function ($l) {
            $nameParts = preg_split('/[\s,]+/', trim($l->grantee), 2);
            return [
                'firstName' => $nameParts[0] ?? '',
                'lastName'  => $nameParts[1] ?? ($nameParts[0] ?? ''),
                'address'   => $l->address ?? '',
                'city'      => $l->city ?? '',
                'state'     => $l->state ?? '',
                'zip'       => $l->zip ?? '',
            ];
        })->toArray();

        try {
            $result = $service->batchTrace($requests);
            $this->traceQueueId = $result['queue_id'] ?? null;
            $this->traceQueuePending = true;
            $this->tracing = false;

            if ($this->traceQueueId) {
                $this->successMessage = "Tracerfy batch submitted! Queue #{$this->traceQueueId} — {$this->traceTotal} leads. Click 'Check Results' when ready.";
            }

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'skip-trace',
                'leads_found' => $this->traceTotal,
                'cost_estimate' => $this->traceTotal * 0.02,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tracerfy skip trace error', ['error' => $e->getMessage()]);
            $this->traceError = $e->getMessage();
            $this->tracing = false;
        }
    }

    public function checkTraceResults()
    {
        if (!$this->traceQueueId) {
            $this->traceError = 'No pending trace queue.';
            return;
        }

        $service = app(TracerfyService::class);

        try {
            // Check all queues to see if our queue is done
            $queues = $service->getQueues();
            $ourQueue = collect($queues)->firstWhere('id', $this->traceQueueId);

            if (!$ourQueue || ($ourQueue['pending'] ?? true)) {
                $this->traceError = "Queue #{$this->traceQueueId} is still processing. Try again in a moment.";
                return;
            }

            // Queue is done — fetch results
            $results = $service->getQueue($this->traceQueueId);
            if (empty($results)) {
                $this->traceError = 'No results returned.';
                return;
            }

            $leads = AtlasLead::whereIn('status', ['new', 'searched'])->get();
            $tracedCount = 0;
            $this->traceResults = [];

            foreach ($results as $i => $result) {
                $normalized = $service->normalizeResult($result);
                $lead = $leads[$i] ?? null;

                if ($lead) {
                    $lead->update([
                        'phone_1' => $normalized['phones'][0]['number'] ?? null,
                        'phone_1_type' => $normalized['phones'][0]['type'] ?? null,
                        'phone_2' => $normalized['phones'][1]['number'] ?? null,
                        'phone_2_type' => $normalized['phones'][1]['type'] ?? null,
                        'phone_3' => $normalized['phones'][2]['number'] ?? null,
                        'phone_3_type' => $normalized['phones'][2]['type'] ?? null,
                        'phone_4' => $normalized['phones'][3]['number'] ?? null,
                        'phone_4_type' => $normalized['phones'][3]['type'] ?? null,
                        'phone_5' => $normalized['phones'][4]['number'] ?? null,
                        'phone_5_type' => $normalized['phones'][4]['type'] ?? null,
                        'phone_confidence' => $normalized['confidence'],
                        'email_1' => $normalized['emails'][0] ?? null,
                        'email_2' => $normalized['emails'][1] ?? null,
                        'email_3' => $normalized['emails'][2] ?? null,
                        'status' => count($normalized['phones']) > 0 ? 'traced' : 'searched',
                        'traced_at' => now(),
                    ]);

                    if (count($normalized['phones']) > 0) $tracedCount++;

                    $this->traceResults[] = [
                        'id' => $lead->id,
                        'name' => $lead->grantee,
                        'existingPhone' => $lead->existing_phone,
                        'newPhones' => $normalized['phones'],
                        'emails' => $normalized['emails'],
                        'confidence' => $normalized['confidence'],
                    ];
                }
            }

            $this->traceQueuePending = false;
            $this->traceProgress = $this->traceTotal;
            $this->successMessage = "Skip trace complete: {$tracedCount}/{$this->traceTotal} leads matched.";

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'skip-trace',
                'leads_found' => $this->traceTotal,
                'leads_traced' => $tracedCount,
                'cost_estimate' => $this->traceTotal * 0.02,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tracerfy results error', ['error' => $e->getMessage()]);
            $this->traceError = $e->getMessage();
        }
    }

    // ─── AI Text Parse ──────────────────────────────────
    public function parseText()
    {
        $this->aiError = '';
        $this->aiResults = null;
        $this->aiSelected = [];

        if (strlen(trim($this->pasteText)) < 20) {
            $this->aiError = 'Please paste at least a few lines of deed search results.';
            return;
        }

        $this->aiParsing = true;

        try {
            $service = app(AtlasAIService::class);
            $results = $service->parseText($this->pasteText, $this->aiCounty, $this->aiState);
            $this->aiResults = $results;
            $this->aiSelected = array_keys($results);

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'ai-text',
                'county' => $this->aiCounty,
                'state' => $this->aiState,
                'leads_found' => count($results),
                'raw_input_preview' => substr($this->pasteText, 0, 5000),
            ]);
        } catch (\Throwable $e) {
            $this->aiError = 'AI parsing failed: ' . $e->getMessage();
        }

        $this->aiParsing = false;
    }

    public function importAiResults()
    {
        if (!$this->aiResults) return;
        $count = 0;

        foreach ($this->aiSelected as $idx) {
            if (!isset($this->aiResults[$idx])) continue;
            $r = $this->aiResults[$idx];

            AtlasLead::create([
                'grantee' => $r['grantee'] ?? 'Unknown',
                'grantor' => $r['grantor'] ?? '',
                'county' => $this->aiCounty,
                'state' => $this->aiState,
                'deed_date' => !empty($r['date']) ? $r['date'] : null,
                'address' => $r['address'] ?? null,
                'instrument' => $r['instrument'] ?? null,
                'deed_type' => $r['type'] ?? null,
                'status' => 'new',
                'source' => 'ai-text',
                'created_by' => auth()->id(),
            ]);
            $count++;
        }

        $this->successMessage = "{$count} leads imported from AI text parse.";
        $this->aiResults = null;
        $this->aiSelected = [];
        $this->pasteText = '';
    }

    // ─── PDF Upload ─────────────────────────────────────
    public function updatedPdfFiles()
    {
        $this->validate(['pdfFiles.*' => 'file|mimes:pdf|max:10240']);
        foreach ($this->pdfFiles as $file) {
            $this->pdfQueue[] = $file;
        }
        $this->pdfFiles = [];
    }

    public function removePdfFromQueue(int $index)
    {
        unset($this->pdfQueue[$index]);
        $this->pdfQueue = array_values($this->pdfQueue);
    }

    public function clearPdfQueue()
    {
        $this->pdfQueue = [];
    }

    public function parsePDFs()
    {
        $this->pdfError = '';
        $this->pdfResults = null;
        $this->pdfSelected = [];

        if (empty($this->pdfQueue)) {
            $this->pdfError = 'Please upload at least one PDF.';
            return;
        }

        $this->pdfParsing = true;
        $allResults = [];

        try {
            $service = app(AtlasAIService::class);

            foreach ($this->pdfQueue as $file) {
                $base64 = base64_encode(file_get_contents($file->getRealPath()));
                $results = $service->parsePDF($base64, $this->pdfCounty, $this->pdfState);

                foreach ($results as &$r) {
                    $r['_filename'] = $file->getClientOriginalName();
                }
                $allResults = array_merge($allResults, $results);
            }

            $this->pdfResults = $allResults;
            $this->pdfSelected = array_keys($allResults);

            AtlasParseLog::create([
                'user_id' => auth()->id(),
                'parse_type' => 'ai-pdf',
                'county' => $this->pdfCounty,
                'state' => $this->pdfState,
                'leads_found' => count($allResults),
                'files_processed' => count($this->pdfQueue),
            ]);

            $this->pdfQueue = [];
        } catch (\Throwable $e) {
            $this->pdfError = 'PDF parsing failed: ' . $e->getMessage();
        }

        $this->pdfParsing = false;
    }

    public function importPdfResults()
    {
        if (!$this->pdfResults) return;
        $count = 0;

        foreach ($this->pdfSelected as $idx) {
            if (!isset($this->pdfResults[$idx])) continue;
            $r = $this->pdfResults[$idx];

            AtlasLead::create([
                'grantee' => $r['grantee'] ?? 'Unknown',
                'grantor' => $r['grantor'] ?? '',
                'county' => $this->pdfCounty,
                'state' => $this->pdfState,
                'deed_date' => !empty($r['date']) ? $r['date'] : null,
                'address' => $r['address'] ?? null,
                'instrument' => $r['instrument'] ?? null,
                'deed_type' => $r['type'] ?? null,
                'status' => 'new',
                'source' => 'ai-pdf',
                'source_filename' => $r['_filename'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $count++;
        }

        $this->successMessage = "{$count} leads imported from PDF upload.";
        $this->pdfResults = null;
        $this->pdfSelected = [];
    }

    // ─── Lead Management ────────────────────────────────
    public function updateStatus(int $leadId, string $status)
    {
        AtlasLead::where('id', $leadId)->update(['status' => $status]);
    }

    public function deleteLead(int $leadId)
    {
        AtlasLead::where('id', $leadId)->delete();
        $this->successMessage = 'Lead deleted.';
    }

    public function saveLead()
    {
        $this->validate(['formName' => 'required|string|max:255']);

        AtlasLead::create([
            'grantee' => $this->formName,
            'grantor' => $this->formResort ?: null,
            'existing_phone' => $this->formPhone ?: null,
            'address' => $this->formAddress ?: null,
            'city' => $this->formCity ?: null,
            'state' => $this->formState ?: null,
            'zip' => $this->formZip ?: null,
            'status' => 'new',
            'source' => 'manual',
            'created_by' => auth()->id(),
        ]);

        $this->successMessage = 'Lead added manually.';
        $this->showAddForm = false;
        $this->formName = $this->formPhone = $this->formResort = '';
        $this->formAddress = $this->formCity = $this->formState = $this->formZip = '';
    }

    // ─── Settings ───────────────────────────────────────
    public function saveTracerfyKey()
    {
        if (empty(trim($this->tracerfyKey))) {
            $this->errorMessage = 'Please enter a valid API key.';
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::table('crm_settings')->updateOrInsert(
                ['key' => 'tracerfy.api_key'],
                ['value' => json_encode(encrypt($this->tracerfyKey))]
            );
            $this->keyIsSaved = true;
            $this->successMessage = 'Tracerfy API key saved securely.';
        } catch (\Throwable $e) {
            config(['services.tracerfy.key' => $this->tracerfyKey]);
            $this->keyIsSaved = true;
            $this->successMessage = 'API key saved for this session.';
        }
    }

    public function saveAnthropicKey()
    {
        if (empty(trim($this->anthropicKey))) {
            $this->errorMessage = 'Please enter a valid API key.';
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::table('crm_settings')->updateOrInsert(
                ['key' => 'anthropic.api_key'],
                ['value' => json_encode(encrypt($this->anthropicKey))]
            );
            $this->aiKeyIsSaved = true;
            $this->successMessage = 'Anthropic API key saved securely.';
        } catch (\Throwable $e) {
            config(['services.anthropic.key' => $this->anthropicKey]);
            $this->aiKeyIsSaved = true;
            $this->successMessage = 'AI key saved for this session.';
        }
    }

    public function setTabWithCounty(string $tab, int $countyIdx)
    {
        $counties = $this->getCountiesProperty();
        if (isset($counties[$countyIdx])) {
            $c = $counties[$countyIdx];
            if ($tab === 'parser') {
                $this->aiCounty = $c['county'];
                $this->aiState = $c['state'];
            } elseif ($tab === 'pdf') {
                $this->pdfCounty = $c['county'];
                $this->pdfState = $c['state'];
            }
        }
        $this->activeTab = $tab;
    }

    public function clearMessages()
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    // ─── Counties ───────────────────────────────────────
    public function getCountiesProperty(): array
    {
        return [
            ['state' => 'FL', 'county' => 'Orange', 'city' => 'Orlando', 'resorts' => 'Westgate, Marriott Grande Vista, Sheraton Vistana, Wyndham Bonnet Creek, HGV', 'url' => 'https://or.occompt.com/recorder/web/login.jsp', 'docType' => 'Deed / Official Records', 'tip' => 'Search Official Records → Document Type: DEED → Grantor = resort name'],
            ['state' => 'FL', 'county' => 'Osceola', 'city' => 'Kissimmee', 'resorts' => 'Westgate Town Center, Orange Lake, Mystic Dunes', 'url' => 'https://www.osceolaclerk.com/Official-Records-Search', 'docType' => 'Official Records', 'tip' => 'Official Records → Doc Type = DEED → Search by grantor'],
            ['state' => 'FL', 'county' => 'Polk', 'city' => 'Davenport', 'resorts' => 'Westgate River Ranch, Fantasy World', 'url' => 'https://www.polkcountyclerk.net/records-search/', 'docType' => 'Official Records', 'tip' => 'Search Official Records by grantor name'],
            ['state' => 'FL', 'county' => 'Volusia', 'city' => 'Daytona Beach', 'resorts' => 'Wyndham Ocean Walk, Hawaiian Inn', 'url' => 'https://ecr.volusia.org/web/login.jsp', 'docType' => 'Official Records', 'tip' => 'Document Search → Type: DEED'],
            ['state' => 'FL', 'county' => 'Miami-Dade', 'city' => 'Miami Beach', 'resorts' => "Marriott Doral, Trump Int'l", 'url' => 'https://www2.miami-dadeclerk.com/officialrecords/', 'docType' => 'Official Records', 'tip' => 'Search by Doc Type and grantor'],
            ['state' => 'FL', 'county' => 'Broward', 'city' => 'Fort Lauderdale', 'resorts' => "Wyndham Santa Barbara, Marriott BeachPlace", 'url' => 'https://officialrecords.broward.org/AcclaimWeb/', 'docType' => 'Official Records', 'tip' => 'AcclaimWeb → Doc Type: DEED'],
            ['state' => 'SC', 'county' => 'Horry', 'city' => 'Myrtle Beach', 'resorts' => 'Sheraton Broadway, Marriott OceanWatch, Wyndham', 'url' => 'https://horrycounty.org/Online-Services/Register-of-Deeds', 'docType' => 'Deed Records', 'tip' => 'Register of Deeds → Search by grantor'],
            ['state' => 'SC', 'county' => 'Beaufort', 'city' => 'Hilton Head', 'resorts' => "Marriott SurfWatch, HGV", 'url' => 'https://www.beaufortcountysc.gov/register-of-deeds/', 'docType' => 'Deed Records', 'tip' => 'Register of Deeds search'],
            ['state' => 'HI', 'county' => 'Maui', 'city' => 'Lahaina', 'resorts' => "Marriott Maui Ocean Club, Westin Ka'anapali", 'url' => 'https://boc.ehawaii.gov/lbsearch/', 'docType' => 'Bureau of Conveyances', 'tip' => 'Land Bureau Search → Regular System'],
            ['state' => 'HI', 'county' => 'Honolulu', 'city' => 'Waikiki', 'resorts' => 'Hilton Hawaiian Village, Marriott Ko Olina', 'url' => 'https://boc.ehawaii.gov/lbsearch/', 'docType' => 'Bureau of Conveyances', 'tip' => 'Land Bureau Search → Regular System'],
            ['state' => 'NV', 'county' => 'Clark', 'city' => 'Las Vegas', 'resorts' => "HGV, Marriott Grand Chateau, Wyndham Desert Blue", 'url' => 'https://recorder.clarkcountynv.gov/AcclaimWeb/', 'docType' => 'Official Records', 'tip' => 'AcclaimWeb → Doc Type: DEED TIMESHARE'],
            ['state' => 'MO', 'county' => 'Taney', 'city' => 'Branson', 'resorts' => 'Wyndham Branson, Welk Resorts', 'url' => 'https://www.taneycountyrecorder.com/', 'docType' => 'Deed Records', 'tip' => 'Recorder search for deed transfers'],
            ['state' => 'CO', 'county' => 'Summit', 'city' => 'Breckenridge', 'resorts' => "Grand Timber, Marriott Mountain Valley", 'url' => 'https://www.summitcountyco.gov/recorder', 'docType' => 'Clerk & Recorder', 'tip' => 'Search by grantor for resort names'],
            ['state' => 'CO', 'county' => 'Eagle', 'city' => 'Vail', 'resorts' => "Marriott StreamSide, Hyatt Residence Club", 'url' => 'https://www.eaglecounty.us/Clerk/Recording/', 'docType' => 'Clerk & Recorder', 'tip' => 'Search grantor for resort deeds'],
            ['state' => 'UT', 'county' => 'Summit', 'city' => 'Park City', 'resorts' => "Marriott MountainSide, Westgate Park City", 'url' => 'https://www.summitcounty.org/215/Recorder', 'docType' => 'Recorder', 'tip' => 'Recorder search → grantor = resort'],
            ['state' => 'VA', 'county' => 'James City', 'city' => 'Williamsburg', 'resorts' => "Wyndham Kingsgate, Marriott Manor Club", 'url' => 'https://www.jamescitycountyva.gov/688/Circuit-Court-Clerk', 'docType' => 'Circuit Court', 'tip' => 'Land records via Circuit Court'],
            ['state' => 'AZ', 'county' => 'Maricopa', 'city' => 'Scottsdale', 'resorts' => "Marriott Canyon Villas, Westin Kierland", 'url' => 'https://recorder.maricopa.gov/', 'docType' => 'Recorder', 'tip' => 'Search by document type and grantor'],
            ['state' => 'CA', 'county' => 'Riverside', 'city' => 'Palm Desert', 'resorts' => "Marriott Desert Springs, Westin Mission Hills", 'url' => 'https://www.rivcoacr.org/', 'docType' => 'Recorder', 'tip' => 'County Recorder → grant deeds'],
            ['state' => 'CA', 'county' => 'Placer', 'city' => 'Lake Tahoe', 'resorts' => "Marriott Timber Lodge, Hyatt Regency", 'url' => 'https://www.placer.ca.gov/2925/Recorder', 'docType' => 'Recorder', 'tip' => 'Recorder search → deed transfers'],
        ];
    }

    // ─── Render ─────────────────────────────────────────
    public function render()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'master_admin') {
            abort(403);
        }

        // Auto-migrate if needed (new table OR missing v4 columns)
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('atlas_leads')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } else {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing('atlas_leads');
                // Each ALTER in its own try-catch so one failure doesn't block others
                $statements = [];
                if (!in_array('existing_phone', $cols)) $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `existing_phone` VARCHAR(20) NULL";
                if (!in_array('phone_1', $cols)) {
                    foreach (['phone_1','phone_1_type','phone_2','phone_2_type','phone_3','phone_3_type','phone_4','phone_4_type','phone_5','phone_5_type'] as $c) {
                        $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `{$c}` VARCHAR(20) NULL";
                    }
                    $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `phone_confidence` VARCHAR(10) NULL";
                }
                if (!in_array('email_1', $cols)) {
                    $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `email_1` VARCHAR(255) NULL";
                    $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `email_2` VARCHAR(255) NULL";
                    $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `email_3` VARCHAR(255) NULL";
                }
                if (!in_array('city', $cols)) $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `city` VARCHAR(100) NULL";
                if (!in_array('zip', $cols)) $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `zip` VARCHAR(10) NULL";
                if (!in_array('traced_at', $cols)) $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `traced_at` TIMESTAMP NULL";
                if (!in_array('source_filename', $cols)) $statements[] = "ALTER TABLE `atlas_leads` ADD COLUMN `source_filename` VARCHAR(255) NULL";
                // Convert enums to varchar so 'sheets' and 'traced' values work
                $statements[] = "ALTER TABLE `atlas_leads` MODIFY COLUMN `status` VARCHAR(20) DEFAULT 'new'";
                $statements[] = "ALTER TABLE `atlas_leads` MODIFY COLUMN `source` VARCHAR(20) DEFAULT 'manual'";
                foreach ($statements as $sql) {
                    try { \Illuminate\Support\Facades\DB::statement($sql); } catch (\Throwable $e) {}
                }
            }
            // Ensure parse_logs table
            if (!\Illuminate\Support\Facades\Schema::hasTable('atlas_parse_logs')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } else {
                $logCols = \Illuminate\Support\Facades\Schema::getColumnListing('atlas_parse_logs');
                $logStatements = [];
                if (!in_array('leads_traced', $logCols)) $logStatements[] = "ALTER TABLE `atlas_parse_logs` ADD COLUMN `leads_traced` INT DEFAULT 0";
                if (!in_array('cost_estimate', $logCols)) $logStatements[] = "ALTER TABLE `atlas_parse_logs` ADD COLUMN `cost_estimate` DECIMAL(8,2) NULL";
                if (!in_array('files_processed', $logCols)) $logStatements[] = "ALTER TABLE `atlas_parse_logs` ADD COLUMN `files_processed` INT DEFAULT 0";
                $logStatements[] = "ALTER TABLE `atlas_parse_logs` MODIFY COLUMN `parse_type` VARCHAR(20) NOT NULL";
                foreach ($logStatements as $sql) {
                    try { \Illuminate\Support\Facades\DB::statement($sql); } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Atlas migration: ' . $e->getMessage());
        }

        $leads = collect();
        $stats = ['total' => 0, 'new' => 0, 'searched' => 0, 'traced' => 0, 'imported' => 0];
        $recentLogs = collect();

        try {
            $leads = AtlasLead::query()
                ->when($this->filterStatus !== 'ALL', fn($q) => $q->where('status', $this->filterStatus))
                ->when($this->searchQuery, fn($q) => $q->search($this->searchQuery))
                ->orderByDesc('created_at')
                ->paginate(25);

            $stats = [
                'total' => AtlasLead::count(),
                'new' => AtlasLead::where('status', 'new')->count(),
                'searched' => AtlasLead::where('status', 'searched')->count(),
                'traced' => AtlasLead::where('status', 'traced')->count(),
                'imported' => AtlasLead::where('status', 'imported')->count(),
            ];

            $recentLogs = AtlasParseLog::with('user')->orderByDesc('created_at')->limit(10)->get();
        } catch (\Throwable $e) {
            Log::warning('Atlas query error: ' . $e->getMessage());
        }

        $counties = $this->getCountiesProperty();
        $filteredCounties = collect($counties)->filter(function ($c) {
            if (!$this->countySearch) return true;
            $s = strtolower($this->countySearch);
            return str_contains(strtolower($c['county']), $s)
                || str_contains(strtolower($c['state']), $s)
                || str_contains(strtolower($c['city']), $s)
                || str_contains(strtolower($c['resorts']), $s);
        })->values();

        $traceConfigured = app(TracerfyService::class)->isConfigured();
        $aiConfigured = app(AtlasAIService::class)->isConfigured();

        // Get Tracerfy balance if configured
        $traceBalance = null;
        if ($traceConfigured) {
            try {
                $analytics = app(TracerfyService::class)->getAnalytics();
                $traceBalance = $analytics['balance'] ?? null;
            } catch (\Throwable $e) {}
        }

        return view('livewire.atlas-dashboard', compact(
            'leads', 'stats', 'recentLogs', 'counties', 'filteredCounties', 'traceConfigured', 'aiConfigured', 'traceBalance'
        ));
    }
}
