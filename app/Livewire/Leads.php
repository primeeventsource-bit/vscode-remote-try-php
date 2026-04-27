<?php

namespace App\Livewire;

use App\Livewire\Concerns\SendsTransferDm;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadDuplicate;
use App\Models\LeadImportBatch;
use App\Models\LeadImportFailure;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Jobs\ProcessLeadImportChunk;
use App\Services\LeadDuplicateService;
use App\Services\PipelineEventService;
use App\Services\PipelineStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Leads')]
class Leads extends Component
{
    use WithFileUploads, SendsTransferDm, WithPagination;

    private const MAX_IMPORT_ROWS = 100000;
    private const IMPORT_CHUNK_SIZE = 500;

    // ── Filters & Search ────────────────────────────────────
    public string $search = '';
    public string $filter = 'all';
    public string $resortFilter = 'all';
    #[\Livewire\Attributes\Url(as: 'src_file', except: 'all')]
    public string $sourceFileFilter = 'all'; // 'all' or exact source_file_name; admin/master_admin only
    public string $fronterFilter = 'all';
    public string $ageFilter = 'all';
    public string $duplicateFilter = 'all';
    public string $roleFilter = 'all';
    public int $perPage = 25;

    // ── Lead Selection & Detail ─────────────────────────────
    public ?int $selectedLead = null;
    public string $transferCloser = '';
    public string $callbackDateTime = '';
    public array $selectedLeads = [];
    public string $bulkFronter = '';
    // Filter-wide bulk: when true, bulk actions ignore $selectedLeads and run
    // against the entire current filter result (capped at config('leads.bulk_action_cap')).
    public bool $bulkSelectAllMatching = false;
    // Per-action working state for the bulk modals
    public string $bulkAssigneeId = '';
    public string $bulkDispositionValue = '';
    public string $bulkDeleteConfirm = '';
    public ?string $bulkAction = null; // 'assign' | 'disposition' | 'delete' (which modal is open)

    // ── Add Lead ────────────────────────────────────────────
    public bool $showAddModal = false;
    public array $newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => '', 'email' => ''];

    // ── Import ──────────────────────────────────────────────
    public bool $showImportModal = false;
    public string $csvText = '';
    public $leadFile = null;
    public string $importStatus = '';
    public string $importError = '';
    public string $duplicateStrategy = 'flag'; // skip, flag, import_all

    // ── Edit Lead ───────────────────────────────────────────
    public bool $showEditModal = false;
    public array $editForm = [];
    public string $leadSaveMessage = '';

    // ── Convert to Deal ─────────────────────────────────────
    public bool $showConvertModal = false;
    public array $convertForm = [];
    public string $transferAdmin = '';

    // ── Duplicate Review ────────────────────────────────────
    public bool $showDeleteConfirm = false;
    public ?int $deleteTargetId = null;
    public array $bulkDeleteIds = [];

    // ── Reset page when filters change ──────────────────────
    public function updatedSearch()    { $this->resetPage(); }
    public function updatedFilter()    { $this->resetPage(); }
    public function updatedResortFilter() { $this->resetPage(); }
    public function updatedSourceFileFilter() {
        $this->resetPage();
        // Clear bulk selection so admins don't accidentally act on rows hidden
        // by the new filter. Matches the spec's "filter change clears selection".
        $this->selectedLeads = [];
    }
    public function clearSourceFileFilter(): void
    {
        $this->sourceFileFilter = 'all';
        $this->resetPage();
        $this->selectedLeads = [];
        session()->flash('leads_flash', 'Showing all leads');
    }
    public function updatedFronterFilter() { $this->resetPage(); }
    public function updatedAgeFilter() { $this->resetPage(); }
    public function updatedDuplicateFilter() { $this->resetPage(); }
    public function updatedRoleFilter() { $this->fronterFilter = 'all'; $this->resetPage(); }
    public function updatedPerPage()   { $this->resetPage(); }

    // ── Lead Selection ──────────────────────────────────────

    public function selectLead($id)
    {
        $this->selectedLead = $this->selectedLead === $id ? null : $id;
        $this->dispatch('ai-trainer-entity', module: 'leads', entityId: $this->selectedLead);
    }

    // ── Disposition / Transfer / Convert (UNCHANGED LOGIC) ──

    public function setDisposition($id, $dispo, $closerId = null, $callbackDate = null)
    {
        $lead = Lead::find($id);
        if (!$lead) return;

        $user = auth()->user();
        if (!$user) return;
        // Only assigned user, admins, or closers can set disposition
        if (!$user->hasRole('master_admin', 'admin', 'closer') && $lead->assigned_to !== $user->id) return;

        if ($dispo === 'Transferred to Closer' && $closerId) {
            $fromUser = User::find($lead->original_fronter ?? $lead->assigned_to);
            $toUser = User::find($closerId);

            if ($fromUser && $toUser) {
                PipelineStateService::transferLeadToCloser($lead, $fromUser, $toUser);
                $this->sendTransferDm($closerId, 'Lead', $lead->id, $lead->owner_name ?? 'Unknown', in_array($toUser->role, ['closer', 'closer_panama']) ? 'Closer' : ucfirst(str_replace('_', ' ', $toUser->role)));

                // Log transfer history
                LeadTransfer::create([
                    'lead_id' => $lead->id,
                    'from_user_id' => $fromUser->id,
                    'to_user_id' => $toUser->id,
                    'transferred_by_user_id' => $user->id,
                    'transfer_type' => 'closer',
                    'transfer_reason' => 'Transfer to closer',
                    'disposition_snapshot' => $dispo,
                ]);
            }
        } else {
            $data = ['disposition' => $dispo];
            if ($dispo === 'Callback' && $callbackDate) $data['callback_date'] = $callbackDate;
            $lead->update($data);
        }

        $this->selectedLead = null;
    }

    public function doCallback($id)
    {
        if (!$this->callbackDateTime) return;
        $this->setDisposition($id, 'Callback', null, \Carbon\Carbon::parse($this->callbackDateTime)->format('n/j/Y g:i A'));
        $this->callbackDateTime = '';
    }

    public function transferToCloser($id)
    {
        if (!$this->transferCloser) return;
        $this->setDisposition($id, 'Transferred to Closer', (int) $this->transferCloser);
        $this->transferCloser = '';
    }

    public function openConvertForm($id): void
    {
        $lead = Lead::find($id);
        if (!$lead) return;

        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) return;

        $this->convertForm = [
            'lead_id' => $lead->id,
            'owner_name' => $lead->owner_name ?? '',
            'primary_phone' => $lead->phone1 ?? '',
            'secondary_phone' => $lead->phone2 ?? '',
            'resort_name' => $lead->resort ?? '',
            'resort_city_state' => $lead->resort_location ?? '',
            'city_state_zip' => trim(($lead->city ?? '') . ' ' . ($lead->st ?? '') . ' ' . ($lead->zip ?? '')),
            'mailing_address' => '',
            'email' => $lead->email ?? '',
            'fee' => '',
            'weeks' => '',
            'asking_rental' => '',
            'asking_sale_price' => '',
            'bed_bath' => '',
            'usage' => '',
            'exchange_group' => '',
            'name_on_card' => '',
            'card_type' => '',
            'bank' => '',
            'card_number' => '',
            'exp_date' => '',
            'cv2' => '',
            'billing_address' => '',
            'notes' => '',
            'login_info' => '',
            'verification_num' => '',
        ];
        $this->showConvertModal = true;
    }

    public function convertToDeal(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) return;
        if (empty($this->convertForm['lead_id'])) return;

        $lead = Lead::find($this->convertForm['lead_id']);
        if (!$lead) return;

        try {
            $dealData = [
                'timestamp' => now()->format('n/j/Y'),
                'owner_name' => $this->convertForm['owner_name'],
                'primary_phone' => $this->convertForm['primary_phone'],
                'secondary_phone' => $this->convertForm['secondary_phone'],
                'resort_name' => $this->convertForm['resort_name'],
                'resort_city_state' => $this->convertForm['resort_city_state'],
                'city_state_zip' => $this->convertForm['city_state_zip'],
                'mailing_address' => $this->convertForm['mailing_address'],
                'email' => $this->convertForm['email'],
                'fee' => $this->convertForm['fee'] ?: 0,
                'weeks' => $this->convertForm['weeks'],
                'asking_rental' => $this->convertForm['asking_rental'],
                'asking_sale_price' => $this->convertForm['asking_sale_price'],
                'bed_bath' => $this->convertForm['bed_bath'],
                'usage' => $this->convertForm['usage'],
                'exchange_group' => $this->convertForm['exchange_group'],
                'name_on_card' => $this->convertForm['name_on_card'],
                'card_type' => $this->convertForm['card_type'],
                'bank' => $this->convertForm['bank'],
                'card_number' => $this->convertForm['card_number'],
                'exp_date' => $this->convertForm['exp_date'],
                'billing_address' => $this->convertForm['billing_address'],
                'notes' => $this->convertForm['notes'],
                'login_info' => $this->convertForm['login_info'],
                'verification_num' => $this->convertForm['verification_num'],
            ];

            $deal = PipelineStateService::closeLeadIntoDeal($lead, $user, $dealData);

            $this->showConvertModal = false;
            $this->convertForm = [];
            $this->selectedLead = null;

            session()->flash('message', "Lead converted to Deal #{$deal->id} successfully.");
        } catch (\Throwable $e) {
            Log::error('Convert to deal failed', ['error' => $e->getMessage()]);
        }
    }

    public function transferDealToAdmin(): void
    {
        if (!$this->transferAdmin || !$this->selectedLead) return;

        $lead = Lead::find($this->selectedLead);
        if (!$lead || $lead->disposition !== 'Converted to Deal') return;

        $deal = Deal::where('owner_name', $lead->owner_name)
            ->where('closer', auth()->id())
            ->orderByDesc('id')
            ->first();

        if (!$deal) return;

        $targetId = (int) $this->transferAdmin;
        $closer = auth()->user();
        $targetUser = User::find($targetId);

        if ($closer && $targetUser) {
            PipelineStateService::sendToVerification($deal, $closer, $targetUser);
            $this->sendTransferDm($targetId, 'Deal', $deal->id, $deal->owner_name ?? 'Unknown', 'Verification');

            // Log transfer history
            LeadTransfer::create([
                'lead_id' => $lead->id,
                'from_user_id' => $closer->id,
                'to_user_id' => $targetId,
                'transferred_by_user_id' => $closer->id,
                'transfer_type' => 'verification',
                'transfer_reason' => 'Deal sent to verification',
                'disposition_snapshot' => $lead->disposition,
            ]);
        }

        $this->transferAdmin = '';
        $this->selectedLead = null;
    }

    // ── Lead Edit (UNCHANGED) ───────────────────────────────

    public function editLead($id): void
    {
        $lead = Lead::find($id);
        if (!$lead) return;

        $this->editForm = [
            'id' => $lead->id,
            'owner_name' => $lead->owner_name ?? '',
            'resort' => $lead->resort ?? '',
            'phone1' => $lead->phone1 ?? '',
            'phone2' => $lead->phone2 ?? '',
            'city' => $lead->city ?? '',
            'st' => $lead->st ?? '',
            'zip' => $lead->zip ?? '',
            'resort_location' => $lead->resort_location ?? '',
            'email' => $lead->email ?? '',
        ];
        $this->showEditModal = true;
        $this->leadSaveMessage = '';
    }

    public function updateLead(): void
    {
        if (empty($this->editForm['id'])) return;

        $lead = Lead::find($this->editForm['id']);
        if (!$lead) return;

        try {
            $lead->update([
                'owner_name' => $this->editForm['owner_name'],
                'resort' => $this->editForm['resort'],
                'phone1' => $this->editForm['phone1'],
                'phone2' => $this->editForm['phone2'],
                'city' => $this->editForm['city'],
                'st' => $this->editForm['st'],
                'zip' => $this->editForm['zip'],
                'resort_location' => $this->editForm['resort_location'],
                'email' => $this->editForm['email'] ?? null,
            ]);
            $this->leadSaveMessage = '✓ Lead saved successfully!';
        } catch (\Throwable $e) {
            Log::error('Lead update failed', ['error' => $e->getMessage()]);
            $this->leadSaveMessage = '✕ Failed to save lead.';
        }
    }

    // ── Add Lead ────────────────────────────────────────────

    public function saveLead()
    {
        $user = auth()->user();
        if (!$user || !$user->hasPerm('add_leads')) return;

        $this->validate([
            'newLead.owner_name' => 'required|string|max:255',
            'newLead.resort'     => 'nullable|string|max:255',
            'newLead.phone1'     => 'nullable|string|max:50',
            'newLead.phone2'     => 'nullable|string|max:50',
            'newLead.city'       => 'nullable|string|max:100',
            'newLead.st'         => 'nullable|string|max:10',
            'newLead.zip'        => 'nullable|string|max:20',
            'newLead.email'      => 'nullable|email|max:255',
        ]);

        Lead::create($this->newLead + ['source' => 'manual']);
        $this->reset('newLead', 'showAddModal');
        $this->newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => '', 'email' => ''];
    }

    // ── Enterprise Import (Queued / Chunked) ────────────────

    public function importLeads(): void
    {
        $this->importStatus = '';
        $this->importError = '';

        // Safety net for large CSVs: parsing 100k+ rows can spike memory.
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        // Resolve source to a file path so we can stream it the same way
        // for both uploads and pasted text.
        $sourcePath = null;
        $tempPath = null;
        $sourceLabel = 'pasted_csv';

        if ($this->leadFile) {
            try {
                $this->validate(['leadFile' => 'file|max:102400|mimes:csv,txt']);
                $sourcePath = $this->leadFile->getRealPath();
                $sourceLabel = $this->leadFile->getClientOriginalName();
            } catch (\Throwable $e) {
                $this->importError = 'Invalid file: ' . $e->getMessage();
                return;
            }
        } elseif (trim($this->csvText) !== '') {
            $tempPath = tempnam(sys_get_temp_dir(), 'leadcsv_');
            file_put_contents($tempPath, preg_replace('/^\xEF\xBB\xBF/', '', $this->csvText));
            $sourcePath = $tempPath;
        } else {
            $this->importError = 'Upload a CSV file or paste CSV data before importing.';
            return;
        }

        $sourceLabel = self::stampImportFilename($sourceLabel);

        try {
            $batch = LeadImportBatch::create([
                'user_id' => auth()->id(),
                'original_filename' => $sourceLabel,
                'file_type' => 'csv',
                'total_rows' => 0,
                'status' => 'pending',
                'duplicate_strategy' => $this->duplicateStrategy,
            ]);

            // Stream rows; buffer up to IMPORT_CHUNK_SIZE then dispatch.
            // Hold one chunk back so we know which dispatch is the last
            // (which triggers batch finalization in the job).
            $buffer = [];
            $pending = null;
            $rowOffset = 1;
            $chunkCount = 0;
            $totalRows = 0;

            foreach ($this->streamCsvRows($sourcePath) as $row) {
                $buffer[] = $row;
                $totalRows++;

                if ($totalRows > self::MAX_IMPORT_ROWS) {
                    $batch->delete();
                    $this->importError = 'CSV exceeds the ' . number_format(self::MAX_IMPORT_ROWS) . '-row limit per import. Split the file.';
                    return;
                }

                if (count($buffer) >= self::IMPORT_CHUNK_SIZE) {
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
                $this->importError = 'No importable rows found in the file.';
                return;
            }

            ProcessLeadImportChunk::dispatch($batch->id, $pending['rows'], $pending['offset'], true);
            $chunkCount++;

            $batch->update(['total_rows' => $totalRows]);

            $this->importStatus = "Queued " . number_format($totalRows) . " rows in {$chunkCount} chunk(s). Track progress on the Lead Imports page.";
            $this->leadFile = null;
            $this->csvText = '';
            $this->showImportModal = false;

            Log::info('Lead import queued', ['user' => auth()->id(), 'batch' => $batch->id, 'rows' => $totalRows, 'chunks' => $chunkCount]);
        } catch (\Throwable $e) {
            Log::error('Lead import failed', ['error' => $e->getMessage(), 'user' => auth()->id()]);
            $this->importError = 'Import failed: ' . $e->getMessage();
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    // Keep legacy method for backward compatibility
    public function importCsv()
    {
        $this->importLeads();
    }

    /**
     * Maps each Lead column to a list of accepted CSV header synonyms (normalized).
     * Header normalization: lowercased, non-alphanumeric stripped.
     * e.g. "Phone Number1" → "phonenumber1", "County/State" → "countystate".
     */
    private const CSV_FIELD_SYNONYMS = [
        'resort'          => ['resort', 'resortname', 'property', 'club'],
        'owner_name'      => ['ownername', 'owner', 'name', 'fullname', 'primaryowner', 'owner1', 'ownerone'],
        'owner_name_2'    => ['ownername2', 'owner2', 'ownertwo', 'secondaryowner', 'coowner', 'spouse', 'jointowner'],
        'phone1'          => ['phone1', 'phonenumber1', 'phone', 'phonenumber', 'primaryphone', 'mobile', 'cell'],
        'phone2'          => ['phone2', 'phonenumber2', 'secondaryphone', 'altphone', 'altphonenumber'],
        'city'            => ['city', 'town'],
        'st'              => ['st', 'state'],
        'zip'             => ['zip', 'zipcode', 'postal', 'postalcode'],
        'resort_location' => ['resortlocation', 'location', 'resortcity', 'resortcitystate'],
        'email'           => ['email', 'emailaddress', 'mail'],
        'description'     => ['description', 'notes', 'note', 'comments', 'comment', 'memo', 'remarks'],
        // Combined fields handled in mapCsvRow():
        'countystate'     => ['countystate', 'county_state', 'countyandstate'],
    ];

    private function streamCsvRows(string $path): \Generator
    {
        // Safety net for \r-only line endings (old-Mac / Excel "CSV for Macintosh"):
        // fgetcsv would read the entire file as a single row in that case.
        // \r\n (Windows) and \n (Unix) are handled natively, no rewrite needed.
        $effectivePath = $path;
        $normalizedTemp = null;

        $peek = @file_get_contents($path, false, null, 0, 8192);
        if ($peek !== false && str_contains($peek, "\r") && !str_contains($peek, "\n")) {
            $normalizedTemp = tempnam(sys_get_temp_dir(), 'leadcsv_norm_');
            $in = fopen($path, 'rb');
            $out = fopen($normalizedTemp, 'wb');
            if ($in && $out) {
                while (!feof($in)) {
                    $buf = fread($in, 65536);
                    if ($buf === false) break;
                    fwrite($out, str_replace("\r", "\n", $buf));
                }
                fclose($in);
                fclose($out);
                $effectivePath = $normalizedTemp;
            }
        }

        $handle = @fopen($effectivePath, 'r');
        if (!$handle) {
            if ($normalizedTemp && file_exists($normalizedTemp)) @unlink($normalizedTemp);
            return;
        }

        try {
            $first = fgetcsv($handle);
            if ($first === false) return;

            if (isset($first[0])) {
                $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
            }
            $first = array_map(fn($v) => trim((string) $v), $first);

            // Decide whether the first row is a header by trying to map it.
            // If any cell normalizes to a known synonym, treat it as a header.
            $headerMap = $this->buildHeaderMap($first);
            $hasHeader = !empty($headerMap);

            if (!$hasHeader) {
                // Legacy positional fallback for headerless CSVs in the old
                // resort/owner/phone1/phone2/city/st/zip/resort_location/email order.
                $row = $this->mapCsvRowPositional($first);
                if ($row !== null) yield $row;
            }

            while (($v = fgetcsv($handle)) !== false) {
                $v = array_map(fn($x) => trim((string) $x), $v);
                $row = $hasHeader
                    ? $this->mapCsvRowByHeader($v, $headerMap)
                    : $this->mapCsvRowPositional($v);
                if ($row !== null) yield $row;
            }
        } finally {
            fclose($handle);
            if ($normalizedTemp && file_exists($normalizedTemp)) @unlink($normalizedTemp);
        }
    }

    /**
     * Build a map of [columnIndex => leadField] from the header row.
     * Returns empty array if no header cells matched any known synonym.
     */
    private function buildHeaderMap(array $headerCells): array
    {
        // Flatten synonyms into [synonym => leadField]
        $synonymToField = [];
        foreach (self::CSV_FIELD_SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $syn) {
                $synonymToField[$syn] = $field;
            }
        }

        $map = [];
        foreach ($headerCells as $idx => $cell) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $cell));
            if ($normalized === '') continue;
            if (isset($synonymToField[$normalized])) {
                $field = $synonymToField[$normalized];
                // First match wins — keeps phone1 from being overwritten by a later "phone" header
                if (!in_array($field, $map, true)) {
                    $map[$idx] = $field;
                }
            }
        }
        return $map;
    }

    private function mapCsvRowByHeader(array $v, array $headerMap): ?array
    {
        $row = [
            'resort' => '', 'owner_name' => '', 'owner_name_2' => '', 'phone1' => '', 'phone2' => '',
            'city' => '', 'st' => '', 'zip' => '', 'resort_location' => '', 'email' => '', 'description' => '',
        ];

        foreach ($headerMap as $idx => $field) {
            $val = (string) ($v[$idx] ?? '');

            if ($field === 'countystate') {
                // Split "ORANGE, FL" → city=ORANGE, st=FL (only fill if not already set)
                $parts = array_map('trim', explode(',', $val));
                if ($row['city'] === '' && isset($parts[0])) $row['city'] = $parts[0];
                if ($row['st'] === '' && isset($parts[1])) $row['st'] = $parts[1];
                continue;
            }

            $row[$field] = $val;
        }

        if ($row['owner_name'] === '' && $row['resort'] === '' && $row['phone1'] === '') {
            return null;
        }

        return $row;
    }

    private function mapCsvRowPositional(array $v): ?array
    {
        if (count($v) < 2 || ($v[0] === '' && ($v[1] ?? '') === '')) return null;

        // Legacy 9-column order extended with owner_name_2 (10) and description (11):
        //   resort, owner_name, phone1, phone2, city, st, zip, resort_location, email,
        //   owner_name_2, description
        // Shorter/shuffled CSVs (e.g. 5 cols: resort, owner, phone, email, state)
        // used to land email in phone2 and state in city. Fix that by nudging
        // obvious misplacements based on content rather than position.
        $row = [
            'resort'          => trim($v[0] ?? ''),
            'owner_name'      => trim($v[1] ?? ''),
            'phone1'          => trim($v[2] ?? ''),
            'phone2'          => trim($v[3] ?? ''),
            'city'            => trim($v[4] ?? ''),
            'st'              => trim($v[5] ?? ''),
            'zip'             => trim($v[6] ?? ''),
            'resort_location' => trim($v[7] ?? ''),
            'email'           => trim($v[8] ?? ''),
            'owner_name_2'    => trim($v[9] ?? ''),
            'description'     => trim($v[10] ?? ''),
        ];

        // phone2 that's actually an email -> move to email
        if ($row['phone2'] !== '' && str_contains($row['phone2'], '@') && $row['email'] === '') {
            $row['email'] = $row['phone2'];
            $row['phone2'] = '';
        }
        // phone1 that's actually an email (rare) -> also move
        if ($row['phone1'] !== '' && str_contains($row['phone1'], '@') && $row['email'] === '') {
            $row['email'] = $row['phone1'];
            $row['phone1'] = '';
        }
        // 2-letter code in city with empty st -> it's the state
        if ($row['city'] !== '' && $row['st'] === '' && preg_match('/^[A-Za-z]{2}$/', $row['city'])) {
            $row['st'] = strtoupper($row['city']);
            $row['city'] = '';
        }
        // zip that's actually a 2-letter state code -> shift to st
        if ($row['zip'] !== '' && $row['st'] === '' && preg_match('/^[A-Za-z]{2}$/', $row['zip'])) {
            $row['st'] = strtoupper($row['zip']);
            $row['zip'] = '';
        }

        return $row;
    }

    /**
     * Stamp an upload filename with " (YYYY-MM-DD HH:mm)" so every batch is
     * uniquely identifiable. If a batch with the exact stamped name already
     * exists (concurrent same-minute import), append :ss seconds.
     * Sanitizes path separators, trims, caps at 200 chars before stamping.
     */
    public static function stampImportFilename(string $raw): string
    {
        $clean = trim(str_replace(['\\', '/'], '', $raw));
        if ($clean === '') $clean = 'pasted_csv';
        if (mb_strlen($clean) > 200) $clean = mb_substr($clean, 0, 200);

        $stamped = $clean . ' (' . now()->format('Y-m-d H:i') . ')';
        if (LeadImportBatch::where('original_filename', $stamped)->exists()) {
            $stamped = $clean . ' (' . now()->format('Y-m-d H:i:s') . ')';
        }
        return $stamped;
    }

    public function clearImportState(): void
    {
        $this->csvText = '';
        $this->resetErrorBag('csvText');
        $this->showImportModal = false;
    }

    // ── Bulk Selection (UNCHANGED) ──────────────────────────

    public function selectAllVisibleLeads(): void
    {
        // Selects only the currently rendered page (matches the perPage value).
        $this->selectedLeads = $this->baseLeadsQuery()
            ->limit($this->perPage)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
        $this->bulkSelectAllMatching = false;
    }

    public function selectAllMatchingFilter(): void
    {
        // Switches to filter-wide mode. We do NOT load IDs into $selectedLeads —
        // bulk actions re-query at execution time so the count is always live.
        $this->bulkSelectAllMatching = true;
    }

    public function clearSelectedLeads(): void
    {
        $this->selectedLeads = [];
        $this->bulkSelectAllMatching = false;
        $this->bulkAction = null;
    }

    /**
     * Number of leads the current bulk operation will affect.
     */
    public function bulkTargetCount(): int
    {
        if ($this->bulkSelectAllMatching) {
            return (int) $this->baseLeadsQuery()->limit(config('leads.bulk_action_cap', 10000) + 1)->count();
        }
        return count($this->selectedLeads);
    }

    /**
     * Resolve the bulk target into a query builder (filter-wide) or a where-in
     * query (explicit selection). Caller can chain ->update / ->delete / ->get.
     * Caps filter-wide queries at bulk_action_cap.
     */
    private function bulkTargetQuery()
    {
        $cap = (int) config('leads.bulk_action_cap', 10000);
        if ($this->bulkSelectAllMatching) {
            return $this->baseLeadsQuery()->limit($cap);
        }
        return Lead::query()->whereIn('id', array_slice(array_map('intval', $this->selectedLeads), 0, $cap));
    }

    /**
     * Snapshot of the active filter for audit logging.
     */
    private function bulkFilterSnapshot(): array
    {
        return [
            'search' => $this->search,
            'filter' => $this->filter,
            'resort_filter' => $this->resortFilter,
            'role_filter' => $this->roleFilter,
            'fronter_filter' => $this->fronterFilter,
            'age_filter' => $this->ageFilter,
            'duplicate_filter' => $this->duplicateFilter,
            'source_file_filter' => $this->sourceFileFilter,
        ];
    }

    private function ensureBulkAdmin(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) {
            session()->flash('leads_flash', 'Only Admin or Master Admin can run bulk actions.');
            return false;
        }
        return true;
    }

    private function bulkCapExceeded(): bool
    {
        $cap = (int) config('leads.bulk_action_cap', 10000);
        $count = $this->bulkTargetCount();
        if ($count > $cap) {
            session()->flash('leads_flash', "Filter matches more than {$cap} leads — narrow the filter or use explicit selection.");
            return true;
        }
        if ($count === 0) {
            session()->flash('leads_flash', 'No leads selected.');
            return true;
        }
        return false;
    }

    public function openBulkAction(string $which): void
    {
        if (!$this->ensureBulkAdmin()) return;
        if (!in_array($which, ['assign', 'disposition', 'delete'], true)) return;
        if ($this->bulkCapExceeded()) return;
        $this->bulkAction = $which;
        $this->bulkAssigneeId = '';
        $this->bulkDispositionValue = '';
        $this->bulkDeleteConfirm = '';
    }

    public function cancelBulkAction(): void
    {
        $this->bulkAction = null;
        $this->bulkDeleteConfirm = '';
    }

    public function bulkAssignToUser(): void
    {
        if (!$this->ensureBulkAdmin()) return;
        if ($this->bulkCapExceeded()) { $this->bulkAction = null; return; }
        $assigneeId = (int) $this->bulkAssigneeId;
        $assignee = $assigneeId ? User::find($assigneeId) : null;
        if (!$assignee) {
            session()->flash('leads_flash', 'Pick a user first.');
            return;
        }

        $count = $this->bulkTargetCount();
        try {
            DB::transaction(function () use ($assigneeId) {
                // Use pluck of IDs so we can also write transfer logs without
                // dragging the whole row into memory.
                $ids = (clone $this->bulkTargetQuery())->pluck('id');
                Lead::whereIn('id', $ids)->update(['assigned_to' => $assigneeId]);
                Lead::whereIn('id', $ids)->whereNull('original_fronter')->update(['original_fronter' => $assigneeId]);
            });
            \App\Models\AuditLog::record('leads.bulk_assign', null, null, [
                'assignee_id' => $assigneeId,
                'count' => $count,
                'mode' => $this->bulkSelectAllMatching ? 'filter' : 'explicit',
                'filter' => $this->bulkSelectAllMatching ? $this->bulkFilterSnapshot() : null,
                'lead_ids' => $this->bulkSelectAllMatching ? null : $this->selectedLeads,
            ]);
            session()->flash('leads_flash', "Assigned {$count} leads to {$assignee->name}.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Bulk assign failed', ['error' => $e->getMessage()]);
            session()->flash('leads_flash', 'Bulk assign failed: ' . $e->getMessage());
        }
        $this->clearSelectedLeads();
    }

    public function bulkSetDisposition(): void
    {
        if (!$this->ensureBulkAdmin()) return;
        if ($this->bulkCapExceeded()) { $this->bulkAction = null; return; }
        $dispo = trim($this->bulkDispositionValue);
        if ($dispo === '') {
            session()->flash('leads_flash', 'Pick a disposition first.');
            return;
        }

        $count = $this->bulkTargetCount();
        try {
            DB::transaction(function () use ($dispo) {
                $ids = (clone $this->bulkTargetQuery())->pluck('id');
                Lead::whereIn('id', $ids)->update(['disposition' => $dispo]);
            });
            \App\Models\AuditLog::record('leads.bulk_disposition', null, null, [
                'disposition' => $dispo,
                'count' => $count,
                'mode' => $this->bulkSelectAllMatching ? 'filter' : 'explicit',
                'filter' => $this->bulkSelectAllMatching ? $this->bulkFilterSnapshot() : null,
                'lead_ids' => $this->bulkSelectAllMatching ? null : $this->selectedLeads,
            ]);
            session()->flash('leads_flash', "Updated disposition for {$count} leads.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Bulk disposition failed', ['error' => $e->getMessage()]);
            session()->flash('leads_flash', 'Bulk disposition failed: ' . $e->getMessage());
        }
        $this->clearSelectedLeads();
    }

    public function bulkDeleteLeads(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin')) {
            session()->flash('leads_flash', 'Only Master Admin can bulk-delete leads.');
            return;
        }
        if ($this->bulkDeleteConfirm !== 'DELETE') {
            session()->flash('leads_flash', 'Type DELETE to confirm.');
            return;
        }
        if ($this->bulkCapExceeded()) { $this->bulkAction = null; return; }

        $count = $this->bulkTargetCount();
        try {
            DB::transaction(function () {
                $ids = (clone $this->bulkTargetQuery())->pluck('id');
                Lead::whereIn('id', $ids)->delete(); // soft delete (Lead uses SoftDeletes)
            });
            \App\Models\AuditLog::record('leads.bulk_delete', null, null, [
                'count' => $count,
                'mode' => $this->bulkSelectAllMatching ? 'filter' : 'explicit',
                'filter' => $this->bulkSelectAllMatching ? $this->bulkFilterSnapshot() : null,
                'lead_ids' => $this->bulkSelectAllMatching ? null : $this->selectedLeads,
            ]);
            session()->flash('leads_flash', "Deleted {$count} leads (soft delete; recoverable).");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Bulk delete failed', ['error' => $e->getMessage()]);
            session()->flash('leads_flash', 'Bulk delete failed: ' . $e->getMessage());
        }
        $this->clearSelectedLeads();
    }

    public function bulkExportCsv()
    {
        if (!$this->ensureBulkAdmin()) return;
        if ($this->bulkCapExceeded()) return;

        $count = $this->bulkTargetCount();
        $filename = 'leads_export_' . now()->format('Y-m-d_Hi') . '.csv';
        $columns = ['id', 'resort', 'owner_name', 'owner_name_2', 'phone1', 'phone2', 'city', 'st', 'zip', 'resort_location', 'email', 'description', 'disposition', 'source_file_name', 'assigned_to', 'created_at'];

        \App\Models\AuditLog::record('leads.bulk_export', null, null, [
            'count' => $count,
            'mode' => $this->bulkSelectAllMatching ? 'filter' : 'explicit',
            'filter' => $this->bulkSelectAllMatching ? $this->bulkFilterSnapshot() : null,
            'lead_ids' => $this->bulkSelectAllMatching ? null : $this->selectedLeads,
        ]);

        // Capture state needed inside the streamed callback (Livewire serializes; closures don't).
        $allMatching = $this->bulkSelectAllMatching;
        $cap = (int) config('leads.bulk_action_cap', 10000);
        $explicitIds = array_map('intval', $this->selectedLeads);
        $filters = $this->bulkFilterSnapshot();

        return response()->streamDownload(function () use ($allMatching, $cap, $explicitIds, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query = $allMatching
                ? $this->baseLeadsQuery()->limit($cap)
                : Lead::query()->whereIn('id', array_slice($explicitIds, 0, $cap));

            $query->chunkById(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $r) {
                    $line = [];
                    foreach ($columns as $col) {
                        $line[] = (string) ($r->{$col} ?? '');
                    }
                    fputcsv($out, $line);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function assignSelectedToFronter(): void
    {
        if (count($this->selectedLeads) === 0) {
            $this->addError('bulkFronter', 'Select at least one lead.');
            return;
        }

        if (!$this->bulkFronter) {
            $this->addError('bulkFronter', 'Select a user to assign.');
            return;
        }

        $assigneeId = (int) $this->bulkFronter;
        $assignee = User::find($assigneeId);
        if (!$assignee) {
            $this->addError('bulkFronter', 'Selected user not found.');
            return;
        }

        $currentUser = auth()->user();

        Lead::whereIn('id', $this->selectedLeads)->get()->each(function (Lead $lead) use ($assigneeId, $currentUser) {
            $previousAssignee = $lead->assigned_to;
            $data = ['assigned_to' => $assigneeId];
            if (!$lead->original_fronter) {
                $data['original_fronter'] = $assigneeId;
            }
            $lead->update($data);

            // Log transfer history
            if ($previousAssignee && $previousAssignee !== $assigneeId) {
                LeadTransfer::create([
                    'lead_id' => $lead->id,
                    'from_user_id' => $previousAssignee,
                    'to_user_id' => $assigneeId,
                    'transferred_by_user_id' => $currentUser->id,
                    'transfer_type' => 'assignment',
                    'transfer_reason' => 'Bulk assignment',
                    'disposition_snapshot' => $lead->disposition,
                ]);
            }
        });

        $this->selectedLeads = [];
        $this->bulkFronter = '';
        $this->resetErrorBag('bulkFronter');
    }

    public function unassignSelectedLeads(): void
    {
        if (count($this->selectedLeads) === 0) {
            $this->addError('bulkFronter', 'Select at least one lead.');
            return;
        }

        Lead::whereIn('id', $this->selectedLeads)->update(['assigned_to' => null]);

        $this->selectedLeads = [];
        $this->resetErrorBag('bulkFronter');
    }

    // ── Duplicate Actions ───────────────────────────────────

    public function confirmDeleteLead(int $id): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;

        $this->deleteTargetId = $id;
        $this->showDeleteConfirm = true;
    }

    public function deleteLead(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;
        if (!$this->deleteTargetId) return;

        $lead = Lead::find($this->deleteTargetId);
        if ($lead) {
            $lead->delete(); // Soft delete
            LeadDuplicate::where('lead_id', $lead->id)
                ->orWhere('duplicate_lead_id', $lead->id)
                ->update(['review_status' => 'deleted_duplicate']);
        }

        $this->deleteTargetId = null;
        $this->showDeleteConfirm = false;
    }

    public function cancelDelete(): void
    {
        $this->deleteTargetId = null;
        $this->showDeleteConfirm = false;
    }

    public function keepBothDuplicates(int $duplicateRecordId): void
    {
        $dup = LeadDuplicate::find($duplicateRecordId);
        if ($dup) {
            $dup->update(['review_status' => 'kept_both', 'reviewed_by' => auth()->id()]);
        }
    }

    public function ignoreDuplicate(int $duplicateRecordId): void
    {
        $dup = LeadDuplicate::find($duplicateRecordId);
        if ($dup) {
            $dup->update(['review_status' => 'ignored', 'reviewed_by' => auth()->id()]);
        }
    }

    public function bulkKeepSelected(): void
    {
        if (empty($this->selectedLeads)) return;

        LeadDuplicate::whereIn('lead_id', $this->selectedLeads)
            ->orWhereIn('duplicate_lead_id', $this->selectedLeads)
            ->where('review_status', 'pending')
            ->update(['review_status' => 'kept_both', 'reviewed_by' => auth()->id()]);

        $this->selectedLeads = [];
    }

    public function bulkDeleteSelected(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;
        if (empty($this->selectedLeads)) return;

        // Soft delete selected leads
        Lead::whereIn('id', $this->selectedLeads)->each(fn($lead) => $lead->delete());

        LeadDuplicate::where(function ($q) {
            $q->whereIn('lead_id', $this->selectedLeads)
              ->orWhereIn('duplicate_lead_id', $this->selectedLeads);
        })->where('review_status', 'pending')
          ->update(['review_status' => 'deleted_duplicate', 'reviewed_by' => auth()->id()]);

        $this->selectedLeads = [];
    }

    public function bulkMarkReviewed(): void
    {
        if (empty($this->selectedLeads)) return;

        LeadDuplicate::where(function ($q) {
            $q->whereIn('lead_id', $this->selectedLeads)
              ->orWhereIn('duplicate_lead_id', $this->selectedLeads);
        })->where('review_status', 'pending')
          ->update(['review_status' => 'ignored', 'reviewed_by' => auth()->id()]);

        $this->selectedLeads = [];
    }

    // ── Core Query Builder ──────────────────────────────────

    private function baseLeadsQuery()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');

        // Select only columns needed for list view — avoid loading heavy data
        $query = Lead::query()
            ->select([
                'id', 'resort', 'owner_name', 'phone1', 'phone2', 'email',
                'city', 'st', 'zip', 'resort_location',
                'assigned_to', 'original_fronter', 'disposition',
                'source', 'created_at', 'imported_at', 'import_batch_id',
            ])
            ->orderBy('id', 'desc');

        if (!$isAdmin) {
            $query->where('assigned_to', $user->id);
        }

        // Search
        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q
                ->where('owner_name', 'like', "%$s%")
                ->orWhere('resort', 'like', "%$s%")
                ->orWhere('phone1', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%"));
        }

        // Resort filter
        if ($this->resortFilter !== 'all') {
            $query->where('resort', $this->resortFilter);
        }

        // Source-file filter — admin/master_admin only. Silently ignored for other
        // roles even if URL param is forged; their visibility is already constrained
        // by the assigned_to gate above.
        if ($this->sourceFileFilter !== 'all' && $isAdmin) {
            $query->where('source_file_name', $this->sourceFileFilter);
        }

        // Role filter — restrict to users in a specific role
        if ($this->roleFilter !== 'all') {
            $roleUserIds = User::where('role', $this->roleFilter)->pluck('id');
            $query->whereIn('assigned_to', $roleUserIds);
        }

        // Fronter filter
        if ($this->fronterFilter === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($this->fronterFilter !== 'all') {
            $query->where('assigned_to', (int) $this->fronterFilter);
        }

        // Disposition filter
        if ($this->filter === 'undisposed') {
            $query->whereNull('disposition');
        } elseif ($this->filter === 'transferred') {
            $query->where('disposition', 'like', 'Transferred%');
        }

        // Age filter
        if ($this->ageFilter !== 'all') {
            match ($this->ageFilter) {
                'new' => $query->where('created_at', '>=', now()->startOfDay()),
                'this_week' => $query->where('created_at', '>=', now()->startOfWeek()),
                'last_month' => $query->whereBetween('created_at', [
                    now()->subMonth()->startOfMonth(),
                    now()->subMonth()->endOfMonth(),
                ]),
                'old' => $query->where('created_at', '<', now()->subMonth()->startOfMonth()),
                default => null,
            };
        }

        // Duplicate filter
        if ($this->duplicateFilter !== 'all') {
            match ($this->duplicateFilter) {
                'has_duplicates' => $query->whereIn('id', LeadDuplicate::select('lead_id')->union(LeadDuplicate::select('duplicate_lead_id'))),
                'exact_duplicates' => $query->whereIn('id',
                    LeadDuplicate::where('duplicate_type', 'exact')->select('lead_id')
                        ->union(LeadDuplicate::where('duplicate_type', 'exact')->select('duplicate_lead_id'))
                ),
                'possible_duplicates' => $query->whereIn('id',
                    LeadDuplicate::where('duplicate_type', 'possible')->select('lead_id')
                        ->union(LeadDuplicate::where('duplicate_type', 'possible')->select('duplicate_lead_id'))
                ),
                'pending_review' => $query->whereIn('id',
                    LeadDuplicate::where('review_status', 'pending')->select('lead_id')
                        ->union(LeadDuplicate::where('review_status', 'pending')->select('duplicate_lead_id'))
                ),
                'reviewed' => $query->whereIn('id',
                    LeadDuplicate::where('review_status', '!=', 'pending')->select('lead_id')
                        ->union(LeadDuplicate::where('review_status', '!=', 'pending')->select('duplicate_lead_id'))
                ),
                default => null,
            };
        }

        return $query;
    }

    // ── Render ───────────────────────────────────────────────

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');

        // TRUE SERVER-SIDE PAGINATION — never load all leads into memory
        $leads = $this->baseLeadsQuery()->paginate($this->perPage);

        $resorts = Lead::distinct()->pluck('resort')->filter()->sort();

        // Source-file dropdown — admin/master_admin only. Excludes files with
        // zero current leads via HAVING. Ordered by most-recent import first.
        $sourceFiles = collect();
        if ($isAdmin) {
            try {
                $sourceFiles = Lead::query()
                    ->select('source_file_name')
                    ->selectRaw('COUNT(*) as lead_count')
                    ->selectRaw('MAX(COALESCE(imported_at, created_at)) as last_imported')
                    ->whereNotNull('source_file_name')
                    ->where('source_file_name', '!=', '')
                    ->groupBy('source_file_name')
                    ->having('lead_count', '>', 0)
                    ->orderByDesc('last_imported')
                    ->limit(500)
                    ->get();
            } catch (\Throwable $e) {
                // Migration may not have run yet on this env — fail open with empty list
                $sourceFiles = collect();
            }
        }
        $allActiveUsers = User::orderBy('name')->get();
        $closers = $allActiveUsers; // Show all users in transfer-to-closer dropdown
        $fronters = $allActiveUsers; // Show all users in fronter filter + bulk assign
        $users = $allActiveUsers;
        $roles = $allActiveUsers->pluck('role')->unique()->filter()->sort()->values();
        // Users filtered by selected role for the cascading dropdown
        $roleUsers = $this->roleFilter !== 'all'
            ? $allActiveUsers->where('role', $this->roleFilter)->values()
            : collect();
        $active = $this->selectedLead ? Lead::find($this->selectedLead) : null;

        // Duplicate info for active lead
        $activeDuplicates = null;
        if ($active) {
            $activeDuplicates = LeadDuplicate::where('lead_id', $active->id)
                ->orWhere('duplicate_lead_id', $active->id)
                ->with(['lead', 'duplicateLead'])
                ->limit(20)
                ->get();
        }

        // Fronter stats (admin only) — optimized with DB aggregation
        $fronterStats = [];
        if ($isAdmin) {
            $fronterStats = DB::table('leads')
                ->select('assigned_to')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN disposition IS NULL THEN 1 ELSE 0 END) as undisposed")
                ->selectRaw("SUM(CASE WHEN disposition LIKE 'Transferred%' THEN 1 ELSE 0 END) as transferred")
                ->selectRaw("SUM(CASE WHEN disposition LIKE '%Callback%' THEN 1 ELSE 0 END) as callback")
                ->selectRaw("SUM(CASE WHEN disposition LIKE '%Right Number%' THEN 1 ELSE 0 END) as right_number")
                ->whereNotNull('assigned_to')
                ->whereNull('deleted_at')
                ->groupBy('assigned_to')
                ->get()
                ->map(function ($row) use ($fronters) {
                    $fronter = $fronters->firstWhere('id', $row->assigned_to);
                    if (!$fronter) return null;
                    return [
                        'id' => $row->assigned_to,
                        'name' => $fronter->name,
                        'total' => $row->total,
                        'undisposed' => $row->undisposed,
                        'transferred' => $row->transferred,
                        'callback' => $row->callback,
                        'right_number' => $row->right_number,
                    ];
                })
                ->filter()
                ->values()
                ->toArray();
        }

        // Lazy-loaded counts for dashboard widgets
        $totalLeads = Lead::count();
        $duplicateCounts = null;
        if ($isAdmin) {
            $duplicateCounts = [
                'total' => LeadDuplicate::count(),
                'exact' => LeadDuplicate::where('duplicate_type', 'exact')->count(),
                'possible' => LeadDuplicate::where('duplicate_type', 'possible')->count(),
                'pending' => LeadDuplicate::where('review_status', 'pending')->count(),
            ];
        }

        // Recent imports
        $recentImports = $isAdmin
            ? LeadImportBatch::orderByDesc('id')->limit(5)->get()
            : LeadImportBatch::where('user_id', $user->id)->orderByDesc('id')->limit(5)->get();

        return view('livewire.leads', compact(
            'leads', 'resorts', 'closers', 'fronters', 'users', 'active',
            'isAdmin', 'fronterStats', 'totalLeads', 'duplicateCounts',
            'activeDuplicates', 'recentImports', 'roles', 'roleUsers',
            'sourceFiles'
        ));
    }
}
