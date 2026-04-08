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

    private const MAX_IMPORT_ROWS = 10000;
    private const IMPORT_CHUNK_SIZE = 500;

    // ── Filters & Search ────────────────────────────────────
    public string $search = '';
    public string $filter = 'all';
    public string $resortFilter = 'all';
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

    // ── Add Lead ────────────────────────────────────────────
    public bool $showAddModal = false;
    public array $newLead = ['resort' => '', 'owner_name' => '', 'phone1' => '', 'phone2' => '', 'city' => '', 'st' => '', 'zip' => '', 'resort_location' => '', 'email' => ''];

    // ── Import ──────────────────────────────────────────────
    public bool $showImportModal = false;
    public string $csvText = '';
    public int $importRowsProcessed = 0;
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

        $csvContent = '';

        if ($this->leadFile) {
            try {
                $this->validate(['leadFile' => 'file|max:102400|mimes:csv,txt']);
                $csvContent = file_get_contents($this->leadFile->getRealPath());
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

        $rows = $this->parseCsvToRows($csvContent);

        if (empty($rows)) {
            $this->importError = 'No importable rows found in the file.';
            return;
        }

        try {
            // Create import batch record
            $batch = LeadImportBatch::create([
                'user_id' => auth()->id(),
                'original_filename' => $this->leadFile?->getClientOriginalName() ?? 'pasted_csv',
                'file_type' => 'csv',
                'total_rows' => count($rows),
                'status' => 'pending',
                'duplicate_strategy' => $this->duplicateStrategy,
            ]);

            // Process import chunks synchronously to avoid pending queue issues
            $chunks = array_chunk($rows, self::IMPORT_CHUNK_SIZE);
            $rowOffset = 1;

            foreach ($chunks as $i => $chunk) {
                $isLast = ($i === count($chunks) - 1);
                try {
                    ProcessLeadImportChunk::dispatchSync(
                        $batch->id,
                        $chunk,
                        $rowOffset,
                        $isLast
                    );
                } catch (\Throwable $e) {
                    \Log::error('Failed to process import chunk', ['batch' => $batch->id, 'chunk' => $i, 'error' => $e->getMessage()]);
                    $this->importStatus = "Import partially completed — some chunks failed. Check Import History.";
                    return;
                }
                $rowOffset += count($chunk);
            }

            $batch->refresh();
            $this->importStatus = "Import complete: {$batch->successful_rows} of {$batch->total_rows} rows imported successfully.";
            $this->leadFile = null;
            $this->csvText = '';
            $this->showImportModal = false;

            Log::info('Lead import queued', ['user' => auth()->id(), 'batch' => $batch->id, 'rows' => $batch->total_rows]);
        } catch (\Throwable $e) {
            Log::error('Lead import failed', ['error' => $e->getMessage(), 'user' => auth()->id()]);
            $this->importError = 'Import failed: ' . $e->getMessage();
        }
    }

    // Keep legacy method for backward compatibility
    public function importCsv()
    {
        $this->importLeads();
    }

    private function parseCsvToRows(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (!$lines) return [];

        $startIndex = 0;
        $firstRow = array_map('trim', str_getcsv($lines[0]));
        $h0 = strtolower($firstRow[0] ?? '');
        $h1 = strtolower($firstRow[1] ?? '');

        if (str_contains($h0, 'resort') || str_contains($h1, 'owner') || str_contains($h0, 'email')) {
            $startIndex = 1;
        }

        $rows = [];
        for ($i = $startIndex; $i < count($lines); $i++) {
            $v = array_map('trim', str_getcsv($lines[$i]));
            if (count($v) < 2 || ($v[0] === '' && $v[1] === '')) continue;

            $rows[] = [
                'resort' => $v[0] ?? '',
                'owner_name' => $v[1] ?? '',
                'phone1' => $v[2] ?? '',
                'phone2' => $v[3] ?? '',
                'city' => $v[4] ?? '',
                'st' => $v[5] ?? '',
                'zip' => $v[6] ?? '',
                'resort_location' => $v[7] ?? '',
                'email' => $v[8] ?? '',
            ];
        }

        return $rows;
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

    // ── Bulk Selection (UNCHANGED) ──────────────────────────

    public function selectAllVisibleLeads(): void
    {
        $this->selectedLeads = $this->baseLeadsQuery()
            ->limit(500)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
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
            'activeDuplicates', 'recentImports', 'roles', 'roleUsers'
        ));
    }
}
