<?php

namespace App\Livewire;

use App\Livewire\Concerns\SendsTransferDm;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Leads')]
class Leads extends Component
{
    use WithFileUploads, SendsTransferDm;

    private const MAX_IMPORT_ROWS = 10000;

    public string $search = '';
    public string $filter = 'all';
    public string $resortFilter = 'all';
    public string $fronterFilter = 'all';
    public ?int $selectedLead = null;
    public string $transferCloser = '';
    public string $callbackDateTime = '';
    public bool $showAddModal = false;
    public bool $showImportModal = false;
    public string $csvText = '';
    public int $importRowsProcessed = 0;
    public array $selectedLeads = [];
    public string $bulkFronter = '';
    public array $newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => ''];
    public $leadFile = null;
    public string $importStatus = '';
    public string $importError = '';
    public bool $showEditModal = false;
    public array $editForm = [];
    public bool $showConvertModal = false;
    public array $convertForm = [];
    public string $transferAdmin = '';

    public function selectLead($id) { $this->selectedLead = $this->selectedLead === $id ? null : $id; }

    public function setDisposition($id, $dispo, $closerId = null, $callbackDate = null)
    {
        $lead = Lead::find($id);
        if (!$lead) return;
        $data = ['disposition' => $dispo];
        if ($dispo === 'Transferred to Closer' && $closerId) {
            $data['transferred_to'] = $closerId;
            $data['assigned_to'] = $closerId;
            if (!$lead->original_fronter) $data['original_fronter'] = $lead->assigned_to;
        }
        if ($dispo === 'Callback' && $callbackDate) $data['callback_date'] = $callbackDate;
        $lead->update($data);

        // Auto-DM on lead transfer to closer
        if ($dispo === 'Transferred to Closer' && $closerId) {
            $this->sendTransferDm($closerId, 'Lead', $lead->id, $lead->owner_name ?? 'Unknown', 'Closer');
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
            'email' => '',
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
            $deal = Deal::create([
                'timestamp' => now()->format('n/j/Y'),
                'fronter' => $lead->original_fronter ?? $lead->assigned_to,
                'closer' => $user->id,
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
                'cv2' => $this->convertForm['cv2'],
                'billing_address' => $this->convertForm['billing_address'],
                'notes' => $this->convertForm['notes'],
                'login_info' => $this->convertForm['login_info'],
                'verification_num' => $this->convertForm['verification_num'],
                'status' => 'pending_admin',
                'charged' => 'no',
                'charged_back' => 'no',
            ]);

            // Mark lead as converted
            $lead->update(['disposition' => 'Converted to Deal']);

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

        // Find the deal created from this lead
        $deal = Deal::where('owner_name', $lead->owner_name)
            ->where('closer', auth()->id())
            ->orderByDesc('id')
            ->first();

        if (!$deal) return;

        $adminId = (int) $this->transferAdmin;
        $deal->update([
            'assigned_admin' => $adminId,
            'status' => 'in_verification',
        ]);

        $this->sendTransferDm($adminId, 'Deal', $deal->id, $deal->owner_name ?? 'Unknown', 'Verification');
        $this->transferAdmin = '';
        $this->selectedLead = null;
    }

    public function editLead($id): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;

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
        ];
        $this->showEditModal = true;
    }

    public function updateLead(): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;
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
            ]);
            $this->showEditModal = false;
            $this->editForm = [];
            session()->flash('deal_success', 'Lead updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Lead update failed', ['error' => $e->getMessage()]);
        }
    }

    public function saveLead()
    {
        Lead::create($this->newLead + ['source' => 'manual']);
        $this->reset('newLead', 'showAddModal');
        $this->newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => ''];
    }

    public function importLeads(): void
    {
        $this->importStatus = '';
        $this->importError = '';

        // Determine CSV content from file upload OR pasted text
        $csvContent = '';

        if ($this->leadFile) {
            try {
                $this->validate(['leadFile' => 'file|max:25600|mimes:csv,txt']);
                $csvContent = file_get_contents($this->leadFile->getRealPath());
                // Strip UTF-8 BOM
                $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
            } catch (\Throwable $e) {
                $this->importError = 'Invalid file: ' . $e->getMessage();
                return;
            }
        } elseif (trim($this->csvText) !== '') {
            $csvContent = $this->csvText;
        } else {
            $this->importError = 'Upload a CSV file or paste CSV data before importing.';
            return;
        }

        $lineCount = $this->countImportableRows($csvContent);
        if ($lineCount === 0) {
            $this->importError = 'No importable rows found in the file.';
            return;
        }
        if ($lineCount > self::MAX_IMPORT_ROWS) {
            $this->importError = "CSV has {$lineCount} rows — max is 10,000. Split the file.";
            return;
        }

        try {
            $this->processCsvContent($csvContent);
            $this->leadFile = null;
            $this->csvText = '';
            $this->showImportModal = false;
        } catch (\Throwable $e) {
            Log::error('Lead import failed', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
                'file' => $this->leadFile?->getClientOriginalName(),
            ]);
            $this->importError = 'Import failed: ' . $e->getMessage();
        }
    }

    // Keep legacy method for backward compatibility
    public function importCsv()
    {
        $this->importLeads();
    }

    public function beginCsvImport(int $totalRows = 0): bool
    {
        $this->importRowsProcessed = 0;
        $this->resetErrorBag('csvText');

        if ($totalRows > self::MAX_IMPORT_ROWS) {
            $this->addError('csvText', 'CSV exceeds the 10,000 lead limit. Split the file and import 10,000 or fewer rows at a time.');
            return false;
        }

        return true;
    }

    public function importCsvChunk(array $lines, bool $firstChunk = false): bool
    {
        if (empty($lines)) return true;

        $startIndex = 0;
        if ($firstChunk) {
            $firstRow = array_map('trim', str_getcsv((string) ($lines[0] ?? '')));
            $h0 = strtolower($firstRow[0] ?? '');
            $h1 = strtolower($firstRow[1] ?? '');
            if (str_contains($h0, 'resort') || str_contains($h1, 'owner')) {
                $startIndex = 1;
            }
        }

        for ($i = $startIndex; $i < count($lines); $i++) {
            if ($this->importRowsProcessed >= self::MAX_IMPORT_ROWS) {
                $this->addError('csvText', 'CSV exceeds the 10,000 lead limit. Split the file and import 10,000 or fewer rows at a time.');
                return false;
            }

            $line = trim((string) $lines[$i]);
            if ($line === '') continue;

            $v = array_map('trim', str_getcsv($line));
            if (count($v) < 2) continue;

            Lead::create([
                'resort' => $v[0] ?? '',
                'owner_name' => $v[1] ?? '',
                'phone1' => $v[2] ?? '',
                'phone2' => $v[3] ?? '',
                'city' => $v[4] ?? '',
                'st' => $v[5] ?? '',
                'zip' => $v[6] ?? '',
                'resort_location' => $v[7] ?? '',
                'source' => 'csv',
            ]);

            $this->importRowsProcessed++;
        }

        return true;
    }

    public function clearImportState(): void
    {
        $this->csvText = '';
        $this->importRowsProcessed = 0;
        $this->resetErrorBag('csvText');
        $this->showImportModal = false;
    }

    public function selectAllVisibleLeads(): void
    {
        $this->selectedLeads = $this->baseLeadsQuery()->pluck('id')->map(fn($id) => (int) $id)->toArray();
    }

    public function clearSelectedLeads(): void
    {
        $this->selectedLeads = [];
    }

    public function assignSelectedToFronter(): void
    {
        if (count($this->selectedLeads) === 0) {
            $this->addError('bulkFronter', 'Select at least one lead.');
            return;
        }

        if (!$this->bulkFronter) {
            $this->addError('bulkFronter', 'Select a fronter agent first.');
            return;
        }

        $fronterId = (int) $this->bulkFronter;
        $fronter = User::where('id', $fronterId)->where('role', 'fronter')->first();
        if (!$fronter) {
            $this->addError('bulkFronter', 'Selected user is not a fronter agent.');
            return;
        }

        Lead::whereIn('id', $this->selectedLeads)->get()->each(function (Lead $lead) use ($fronterId) {
            $data = ['assigned_to' => $fronterId];
            if (!$lead->original_fronter) {
                $data['original_fronter'] = $fronterId;
            }
            $lead->update($data);
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

    private function processCsvContent(string $csv): void
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (!$lines) return;

        $startIndex = 0;
        $firstRow = array_map('trim', str_getcsv($lines[0]));
        $h0 = strtolower($firstRow[0] ?? '');
        $h1 = strtolower($firstRow[1] ?? '');

        // Skip header only when it appears to be a real header row.
        if (str_contains($h0, 'resort') || str_contains($h1, 'owner')) {
            $startIndex = 1;
        }

        $batch = [];
        $now = now()->toDateTimeString();
        $imported = 0;
        $skipped = 0;

        for ($i = $startIndex; $i < count($lines); $i++) {
            $v = array_map('trim', str_getcsv($lines[$i]));
            if (count($v) < 2 || ($v[0] === '' && $v[1] === '')) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'resort' => $v[0] ?? '',
                'owner_name' => $v[1] ?? '',
                'phone1' => $v[2] ?? '',
                'phone2' => $v[3] ?? '',
                'city' => $v[4] ?? '',
                'st' => $v[5] ?? '',
                'zip' => $v[6] ?? '',
                'resort_location' => $v[7] ?? '',
                'source' => 'csv',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Insert in chunks of 500 to avoid memory/timeout issues
            if (count($batch) >= 500) {
                Lead::insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            Lead::insert($batch);
            $imported += count($batch);
        }

        $this->importStatus = "Import complete: {$imported} leads imported, {$skipped} rows skipped.";
        Log::info('Lead import success', ['user' => auth()->id(), 'imported' => $imported, 'skipped' => $skipped]);
    }

    private function countImportableRows(string $csv): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (!$lines) return 0;

        $startIndex = 0;
        $firstRow = array_map('trim', str_getcsv($lines[0]));
        $h0 = strtolower($firstRow[0] ?? '');
        $h1 = strtolower($firstRow[1] ?? '');
        if (str_contains($h0, 'resort') || str_contains($h1, 'owner')) {
            $startIndex = 1;
        }

        $count = 0;
        for ($i = $startIndex; $i < count($lines); $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '') continue;
            $v = array_map('trim', str_getcsv($line));
            if (count($v) < 2) continue;
            $count++;
        }

        return $count;
    }

    private function baseLeadsQuery()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $query = Lead::query()->orderBy('id', 'desc');

        if (!$isAdmin) {
            $query->where('assigned_to', $user->id);
        }

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q
                ->where('owner_name', 'like', "%$s%")
                ->orWhere('resort', 'like', "%$s%")
                ->orWhere('phone1', 'like', "%$s%"));
        }

        if ($this->resortFilter !== 'all') {
            $query->where('resort', $this->resortFilter);
        }

        if ($this->fronterFilter === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($this->fronterFilter !== 'all') {
            $query->where('assigned_to', (int) $this->fronterFilter);
        }

        if ($this->filter === 'undisposed') {
            $query->whereNull('disposition');
        }

        if ($this->filter === 'transferred') {
            $query->where('disposition', 'like', 'Transferred%');
        }

        return $query;
    }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $leads = $this->baseLeadsQuery()->get();
        $resorts = Lead::distinct()->pluck('resort')->filter()->sort();
        $closers = User::where('role', 'closer')->get();
        $fronters = User::where('role', 'fronter')->orderBy('name')->get();
        $users = User::all();
        $active = $this->selectedLead ? Lead::find($this->selectedLead) : null;

        $fronterStats = [];
        if ($isAdmin) {
            $leadRows = Lead::query()->select(['assigned_to', 'disposition'])->get();
            foreach ($fronters as $f) {
                $rows = $leadRows->where('assigned_to', $f->id);
                $fronterStats[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'total' => $rows->count(),
                    'undisposed' => $rows->whereNull('disposition')->count(),
                    'transferred' => $rows->filter(fn($r) => str_contains((string) ($r->disposition ?? ''), 'Transferred'))->count(),
                    'callback' => $rows->filter(fn($r) => str_contains((string) ($r->disposition ?? ''), 'Callback'))->count(),
                    'right_number' => $rows->filter(fn($r) => str_contains((string) ($r->disposition ?? ''), 'Right Number'))->count(),
                ];
            }
        }

        return view('livewire.leads', compact('leads', 'resorts', 'closers', 'fronters', 'users', 'active', 'isAdmin', 'fronterStats'));
    }
}
