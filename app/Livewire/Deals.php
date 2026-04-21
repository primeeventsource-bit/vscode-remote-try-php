<?php
namespace App\Livewire;

use App\Livewire\Concerns\SendsTransferDm;
use App\Models\CrmNote;
use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\CrmNoteService;
use App\Services\PipelineStateService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Deals')]
class Deals extends Component
{
    use SendsTransferDm, WithPagination;
    public string $statusFilter = 'all';
    public int $perPage = 25;
    public ?int $selectedDeal = null;
    public bool $showModal = false;
    public bool $showNewDeal = false;
    public array $dealForm = [];
    public array $newDeal = [];
    public string $dispoCallbackDate = '';
    public string $dispoChargedDate = '';
    public string $addCloserId = '';

    // Closer-to-closer transfer
    public string $transferToCloserId = '';
    public string $transferNote = '';
    public bool $showTransferModal = false;

    // Notes system
    public string $noteBody = '';
    public ?int $editingNoteId = null;
    public string $editingNoteBody = '';
    public ?int $sendNoteToChatNoteId = null;
    public string $sendNoteToChatRecipientId = '';
    public string $sendNoteToChatMessage = '';

    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function mount()
    {
        $this->resetForm();
        // Deep-link: /deals?new=1 opens the New Deal modal on load.
        // Lets Clients (and any other page) send users here to create a deal.
        if (request()->boolean('new')) {
            $this->showNewDeal = true;
        }
    }

    /**
     * MySQL strict mode rejects '' for numeric, foreign-key, and date
     * columns. Form selects send empty strings when blank, so coerce known
     * non-string columns to null before save.
     */
    private function sanitizeDealData(array $data): array
    {
        $nullIfEmpty = [
            // FK / numeric
            'fronter', 'closer', 'assigned_admin', 'fee',
            'closer_user_id', 'verification_admin_user_id',
            'fronter_user_id', 'closer_user_id_payroll', 'admin_user_id_payroll',
            'gross_amount', 'collected_amount',
            'closer_comm_amount', 'fronter_comm_amount',
            'snr_deduction', 'vd_deduction', 'closer_net_pay',
            'closer_comm_pct', 'finance_snapshot_id',
            // Dates
            'charged_date', 'closing_date', 'callback_date', 'payment_date',
            'sent_to_verification_at', 'verification_received_at', 'charged_at',
            'payroll_locked_at', 'last_edited_at',
            'week_start_date',
        ];
        foreach ($nullIfEmpty as $col) {
            if (array_key_exists($col, $data) && $data[$col] === '') {
                $data[$col] = null;
            }
        }
        return $data;
    }

    public function resetForm()
    {
        $this->dealForm = ['timestamp' => now()->format('n/j/Y'), 'charged_date' => '', 'was_vd' => 'No', 'fronter' => '', 'closer' => auth()->id(),
            'fee' => '', 'owner_name' => '', 'mailing_address' => '', 'city_state_zip' => '', 'primary_phone' => '', 'secondary_phone' => '',
            'email' => '', 'weeks' => '', 'asking_rental' => '', 'resort_name' => '', 'resort_city_state' => '', 'exchange_group' => '',
            'bed_bath' => '', 'usage' => '', 'asking_sale_price' => '', 'name_on_card' => '', 'card_type' => '', 'bank' => '',
            'card_number' => '', 'exp_date' => '', 'cv2' => '', 'billing_address' => '', 'notes' => '', 'login_info' => '',
            'verification_num' => '', 'assigned_admin' => '', 'status' => 'pending_admin', 'charged' => 'no', 'charged_back' => 'no'];
        $this->newDeal = $this->dealForm;
    }

    public function selectDeal($id)
    {
        $this->selectedDeal = $this->selectedDeal === $id ? null : $id;
        $this->dispatch('ai-trainer-entity', module: 'deals', entityId: $this->selectedDeal);
    }

    public function saveDeal()
    {
        if (!empty($this->dealForm['id'])) {
            $deal = Deal::find($this->dealForm['id']);
            if (!$deal) {
                session()->flash('deal_error', 'Deal not found.');
                return;
            }

            $user = auth()->user();
            // Locked deals: only master admin can edit
            if ($deal->is_locked && !$user?->hasRole('master_admin')) {
                session()->flash('deal_error', 'This deal is locked. Only Master Admin can edit.');
                return;
            }

            $updateData = collect($this->dealForm)
                ->except([
                    'id', 'created_at', 'updated_at',
                    'cv2', 'cv2_2',             // CVV must never be saved
                    'card_number', 'card_number2', // encrypted - managed via migration
                    'card_last4', 'card_brand',    // derived fields
                    'card_last4_2', 'card_brand2', // derived fields
                    'updated_by',                  // managed by Clients component
                ])
                ->toArray();
            $updateData = $this->sanitizeDealData($updateData);
            $updateData['last_edited_by'] = auth()->id();
            $updateData['last_edited_at'] = now();

            try {
                $affected = Deal::where('id', $this->dealForm['id'])->update($updateData);
            } catch (\Throwable $e) {
                Log::error('Deal update failed', ['deal_id' => $this->dealForm['id'], 'error' => $e->getMessage()]);
                session()->flash('deal_error', 'Save failed: ' . $e->getMessage());
                return;
            }

            if ($affected === 0) {
                session()->flash('deal_error', 'Save failed — no rows were updated. Please try again.');
                return;
            }

            // Verify persistence
            $persisted = Deal::find($this->dealForm['id']);
            if (!$persisted || $persisted->last_edited_by !== auth()->id()) {
                session()->flash('deal_error', 'Save verification failed — changes may not have persisted.');
                return;
            }

            session()->flash('deal_success', 'Deal saved successfully.');
        } else {
            $createData = collect($this->newDeal ?: $this->dealForm)
                ->except(['cv2', 'cv2_2', 'card_number', 'card_number2'])
                ->toArray();
            $createData = $this->sanitizeDealData($createData);

            try {
                $deal = Deal::create($createData);
            } catch (\Throwable $e) {
                Log::error('Deal create failed', ['error' => $e->getMessage(), 'data_keys' => array_keys($createData)]);
                session()->flash('deal_error', 'Failed to create deal: ' . $e->getMessage());
                return;
            }

            if (!$deal || !$deal->exists) {
                session()->flash('deal_error', 'Failed to create deal.');
                return;
            }

            session()->flash('deal_success', 'Deal created successfully.');
        }
        $this->showNewDeal = false;
        $this->showModal = false;
        $this->resetForm();
    }

    public function saveAndLockDeal(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) {
            session()->flash('deal_error', 'Unauthorized.');
            return;
        }
        if (empty($this->dealForm['id'])) {
            session()->flash('deal_error', 'No deal selected.');
            return;
        }

        $deal = Deal::find($this->dealForm['id']);
        if (!$deal) {
            session()->flash('deal_error', 'Deal not found.');
            return;
        }
        if ($deal->is_locked && !$user->hasRole('master_admin')) {
            session()->flash('deal_error', 'This deal is locked. Only Master Admin can edit.');
            return;
        }

        $updateData = collect($this->dealForm)
            ->except([
                'id', 'created_at', 'updated_at',
                'cv2', 'cv2_2',
                'card_number', 'card_number2',
                'card_last4', 'card_brand',
                'card_last4_2', 'card_brand2',
                'updated_by',
            ])
            ->toArray();
        $updateData = $this->sanitizeDealData($updateData);
        $updateData['last_edited_by'] = auth()->id();
        $updateData['last_edited_at'] = now();
        $updateData['is_locked'] = true;

        try {
            $affected = Deal::where('id', $deal->id)->update($updateData);
        } catch (\Throwable $e) {
            Log::error('Deal save-and-lock failed', ['deal_id' => $deal->id, 'error' => $e->getMessage()]);
            session()->flash('deal_error', 'Save failed: ' . $e->getMessage());
            return;
        }

        if ($affected === 0) {
            session()->flash('deal_error', 'Save failed — no rows were updated.');
            return;
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('deal_success', 'Deal saved and locked. Only Master Admin can edit now.');
    }

    public function reopenDeal($id): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin')) return;

        Deal::where('id', $id)->update([
            'is_locked' => false,
            'last_edited_by' => $user->id,
            'last_edited_at' => now(),
        ]);
        session()->flash('deal_success', 'Deal reopened for Admin editing.');
    }

    public function editDeal($id)
    {
        $d = Deal::find($id);
        if (!$d) return;

        $user = auth()->user();
        // Admin can only edit if not locked
        if ($d->is_locked && !$user?->hasRole('master_admin')) {
            session()->flash('deal_error', 'This deal is locked. Only Master Admin can edit.');
            return;
        }

        $this->dealForm = $d->toArray();
        $this->showModal = true;
    }

    public function setDealDisposition($id, $disposition, $callbackDate = null, $chargedDate = null): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;

        $deal = Deal::find($id);
        if (!$deal) return;

        $data = [
            'disposition_status' => $disposition,
            'last_edited_by' => $user->id,
            'last_edited_at' => now(),
        ];

        if ($disposition === 'charged') {
            $data['charged'] = 'yes';
            $data['charged_date'] = $chargedDate ?: now()->format('Y-m-d');
            $data['is_locked'] = true;
            $data['status'] = 'charged';
            // Calculate commissions when deal is charged
            $deal->update($data);
            CommissionCalculator::calculate($deal);
            return;
        } elseif ($disposition === 'callback') {
            $data['callback_date'] = $callbackDate;
        } elseif ($disposition === 'declined') {
            $data['status'] = 'cancelled';
        }

        $deal->update($data);
    }

    public function unlockDeal($id): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin')) return;
        Deal::where('id', $id)->update(['is_locked' => false, 'last_edited_by' => $user->id, 'last_edited_at' => now()]);
        session()->flash('deal_success', 'Deal unlocked for Admin editing.');
    }

    public function addCloserToDeal($dealId): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin') || !$this->addCloserId) return;

        $deal = Deal::find($dealId);
        if (!$deal || $deal->charged !== 'yes') return;

        try {
            $count = DealCloser::where('deal_id', $dealId)->count();
            if ($count >= CommissionCalculator::MAX_CLOSERS) {
                session()->flash('deal_error', 'Maximum ' . CommissionCalculator::MAX_CLOSERS . ' closers per deal.');
                return;
            }

            // Ensure original closer is tracked
            if ($count === 0 && $deal->closer) {
                DealCloser::firstOrCreate(
                    ['deal_id' => $dealId, 'user_id' => $deal->closer],
                    ['is_original' => true]
                );
            }

            DealCloser::firstOrCreate(
                ['deal_id' => $dealId, 'user_id' => (int) $this->addCloserId],
                ['is_original' => false]
            );

            CommissionCalculator::calculate($deal);
            $this->addCloserId = '';
            session()->flash('deal_success', 'Closer added — commissions recalculated.');
        } catch (\Throwable $e) {
            session()->flash('deal_error', 'Failed to add closer.');
        }
    }

    public function removeCloserFromDeal($dealId, $userId): void
    {
        $user = auth()->user();
        if (!$user?->hasRole('master_admin', 'admin')) return;

        $closer = DealCloser::where('deal_id', $dealId)->where('user_id', $userId)->first();
        if (!$closer || $closer->is_original) return;

        $closer->delete();
        $deal = Deal::find($dealId);
        if ($deal) CommissionCalculator::calculate($deal);
        session()->flash('deal_success', 'Closer removed — commissions recalculated.');
    }

    // ── Closer-to-Closer Transfer ──────────────────────────────

    public function openTransferModal(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) return;
        if (!$this->selectedDeal) return;

        $this->transferToCloserId = '';
        $this->transferNote = '';
        $this->showTransferModal = true;
    }

    public function transferDealToCloser(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) {
            session()->flash('deal_error', 'Unauthorized.');
            return;
        }

        $deal = Deal::find($this->selectedDeal);
        if (!$deal) {
            session()->flash('deal_error', 'Deal not found.');
            return;
        }

        // Validate target closer
        if (!$this->transferToCloserId) {
            session()->flash('deal_error', 'Please select a closer.');
            return;
        }

        $targetCloserId = (int) $this->transferToCloserId;

        if ($targetCloserId === $user->id) {
            session()->flash('deal_error', 'You cannot transfer to yourself.');
            return;
        }

        $targetCloser = User::where('id', $targetCloserId)->whereIn('role', ['closer', 'closer_panama'])->first();
        if (!$targetCloser) {
            session()->flash('deal_error', 'Selected user is not a valid closer.');
            return;
        }

        // Validate note
        $note = trim($this->transferNote);
        if ($note === '') {
            session()->flash('deal_error', 'A transfer note is required.');
            return;
        }

        try {
            // 1. Update deal + lead + log pipeline event (atomic)
            PipelineStateService::transferDealToCloser($deal, $user, $targetCloser, $note);

            // 2. Send DM to target closer with transfer note
            $this->sendTransferDm(
                $targetCloser->id,
                'Deal',
                $deal->id,
                ($deal->owner_name ?? 'Unknown') . "\nNote: " . $note,
                'Closer'
            );

            $this->showTransferModal = false;
            $this->transferToCloserId = '';
            $this->transferNote = '';

            session()->flash('deal_success', "Deal transferred to {$targetCloser->name}.");
        } catch (\Throwable $e) {
            report($e);
            session()->flash('deal_error', 'Transfer failed: ' . $e->getMessage());
        }
    }

    // ── Notes ────────────────────────────────────────────────────

    public function addDealNote(): void
    {
        $user = auth()->user();
        if (!$user || !Gate::allows('create', CrmNote::class)) {
            session()->flash('deal_error', 'Only ChristianDior Master Admin can add notes.');
            return;
        }

        $body = trim($this->noteBody);
        if ($body === '') {
            session()->flash('deal_error', 'Note cannot be empty.');
            return;
        }

        $deal = Deal::find($this->selectedDeal);
        if (!$deal) return;

        CrmNoteService::createNote($deal, $user, $body);
        $this->noteBody = '';
        session()->flash('deal_success', 'Note added.');
    }

    public function startEditNote(int $noteId): void
    {
        $user = auth()->user();
        $note = CrmNote::find($noteId);
        if (!$note || !$user || !Gate::allows('update', $note)) return;

        $this->editingNoteId = $noteId;
        $this->editingNoteBody = $note->body;
    }

    public function saveEditNote(): void
    {
        $user = auth()->user();
        $note = CrmNote::find($this->editingNoteId);
        if (!$note || !$user || !Gate::allows('update', $note)) {
            session()->flash('deal_error', 'Unauthorized.');
            return;
        }

        $body = trim($this->editingNoteBody);
        if ($body === '') {
            session()->flash('deal_error', 'Note cannot be empty.');
            return;
        }

        CrmNoteService::updateNote($note, $user, $body);
        $this->editingNoteId = null;
        $this->editingNoteBody = '';
        session()->flash('deal_success', 'Note updated.');
    }

    public function cancelEditNote(): void
    {
        $this->editingNoteId = null;
        $this->editingNoteBody = '';
    }

    public function openSendNoteToChat(int $noteId): void
    {
        $user = auth()->user();
        $note = CrmNote::find($noteId);
        if (!$note || !$user || !Gate::allows('sendToChat', $note)) return;

        $this->sendNoteToChatNoteId = $noteId;
        $this->sendNoteToChatRecipientId = '';
        $this->sendNoteToChatMessage = '';
    }

    public function sendNoteToChat(): void
    {
        $user = auth()->user();
        $note = CrmNote::find($this->sendNoteToChatNoteId);
        if (!$note || !$user || !Gate::allows('sendToChat', $note)) {
            session()->flash('deal_error', 'Unauthorized.');
            return;
        }

        if (!$this->sendNoteToChatRecipientId) {
            session()->flash('deal_error', 'Select a recipient.');
            return;
        }

        $recipient = User::where('id', (int) $this->sendNoteToChatRecipientId)
            ->whereIn('role', ['admin', 'master_admin', 'closer'])
            ->first();

        if (!$recipient) {
            session()->flash('deal_error', 'Invalid recipient.');
            return;
        }

        try {
            CrmNoteService::sendNoteToDirectChat(
                $note, $user, $recipient,
                trim($this->sendNoteToChatMessage) ?: null
            );

            $this->sendNoteToChatNoteId = null;
            $this->sendNoteToChatRecipientId = '';
            $this->sendNoteToChatMessage = '';
            session()->flash('deal_success', 'Note sent to chat.');
        } catch (\Throwable $e) {
            report($e);
            session()->flash('deal_error', 'Failed to send note to chat.');
        }
    }

    public function cancelSendNoteToChat(): void
    {
        $this->sendNoteToChatNoteId = null;
        $this->sendNoteToChatRecipientId = '';
        $this->sendNoteToChatMessage = '';
    }

    public function updateStatus($id, $status, $extra = [])
    {
        $deal = Deal::find($id);
        if (!$deal) return;

        $oldStatus = $deal->status;
        $deal->update(array_merge(['status' => $status], $extra));

        // Auto-DM when deal moves to verification
        if ($status === 'in_verification' && $oldStatus !== 'in_verification') {
            $adminId = $extra['assigned_admin'] ?? $deal->assigned_admin;
            if ($adminId) {
                $this->sendTransferDm((int) $adminId, 'Deal', $deal->id, $deal->owner_name ?? 'Unknown', 'Verification');
            }
        }

        // Auto-DM when deal is assigned to a closer
        if (!empty($extra['closer']) && (int) $extra['closer'] !== (int) ($deal->getOriginal('closer') ?? 0)) {
            $this->sendTransferDm((int) $extra['closer'], 'Deal', $deal->id, $deal->owner_name ?? 'Unknown', 'Closer');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $query = Deal::query()->orderBy('id', 'desc');
        if (!$isAdmin) $query->whereNotIn('status', ['charged', 'chargeback']);
        if (in_array($user->role, ['closer', 'closer_panama'])) $query->where('closer', $user->id);
        if (in_array($user->role, ['fronter', 'fronter_panama'])) $query->where('fronter', $user->id);
        if ($this->statusFilter !== 'all') {
            $statusMap = [
                'pending' => 'pending_admin',
                'chargeback' => 'chargeback',
                'charged' => 'charged',
                'cancelled' => 'cancelled',
            ];
            $query->where('status', $statusMap[$this->statusFilter] ?? $this->statusFilter);
        }
        $deals = $query->paginate($this->perPage);
        $users = User::all()->keyBy('id');
        $active = $this->selectedDeal ? Deal::find($this->selectedDeal) : null;
        $dealStatuses = [
            ['value' => 'pending_admin', 'label' => 'Pending Admin', 'color' => '#f59e0b'],
            ['value' => 'in_verification', 'label' => 'In Verification', 'color' => '#3b82f6'],
            ['value' => 'charged', 'label' => 'Charged', 'color' => '#10b981'],
            ['value' => 'chargeback', 'label' => 'Chargeback', 'color' => '#ef4444'],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'color' => '#6b7280'],
        ];
        // Load notes for selected deal
        $dealNotes = collect();
        $canAddNote = false;
        $canSendNoteToChat = false;
        if ($active) {
            try {
                $dealNotes = CrmNote::where('noteable_type', Deal::class)
                    ->where('noteable_id', $active->id)
                    ->orderByDesc('created_at')
                    ->get();
            } catch (\Throwable $e) {
                // Table may not exist yet
            }
            $canAddNote = Gate::allows('create', CrmNote::class);
            $canSendNoteToChat = $user->hasRole('master_admin', 'admin');
        }

        return view('livewire.deals', compact(
            'deals', 'users', 'active', 'dealStatuses', 'isAdmin',
            'dealNotes', 'canAddNote', 'canSendNoteToChat'
        ));
    }
}
