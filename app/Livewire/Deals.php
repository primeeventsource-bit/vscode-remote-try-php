<?php
namespace App\Livewire;

use App\Livewire\Concerns\SendsTransferDm;
use App\Models\Deal;
use App\Models\DealCloser;
use App\Models\User;
use App\Services\CommissionCalculator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Deals')]
class Deals extends Component
{
    use SendsTransferDm;
    public string $statusFilter = 'all';
    public ?int $selectedDeal = null;
    public bool $showModal = false;
    public bool $showNewDeal = false;
    public array $dealForm = [];
    public array $newDeal = [];
    public string $dispoCallbackDate = '';
    public string $dispoChargedDate = '';
    public string $addCloserId = '';

    public function mount() { $this->resetForm(); }

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

    public function selectDeal($id) { $this->selectedDeal = $this->selectedDeal === $id ? null : $id; }

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
            $updateData['last_edited_by'] = auth()->id();
            $updateData['last_edited_at'] = now();

            $affected = Deal::where('id', $this->dealForm['id'])->update($updateData);

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
            $deal = Deal::create($createData);

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
        $updateData['last_edited_by'] = auth()->id();
        $updateData['last_edited_at'] = now();
        $updateData['is_locked'] = true;

        $affected = Deal::where('id', $deal->id)->update($updateData);

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
        if ($user->role === 'closer') $query->where('closer', $user->id);
        if ($user->role === 'fronter') $query->where('fronter', $user->id);
        if ($this->statusFilter !== 'all') {
            $statusMap = [
                'pending' => 'pending_admin',
                'chargeback' => 'chargeback',
                'charged' => 'charged',
                'cancelled' => 'cancelled',
            ];
            $query->where('status', $statusMap[$this->statusFilter] ?? $this->statusFilter);
        }
        $deals = $query->get();
        $users = User::all()->keyBy('id');
        $active = $this->selectedDeal ? Deal::find($this->selectedDeal) : null;
        $dealStatuses = [
            ['value' => 'pending_admin', 'label' => 'Pending Admin', 'color' => '#f59e0b'],
            ['value' => 'in_verification', 'label' => 'In Verification', 'color' => '#3b82f6'],
            ['value' => 'charged', 'label' => 'Charged', 'color' => '#10b981'],
            ['value' => 'chargeback', 'label' => 'Chargeback', 'color' => '#ef4444'],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'color' => '#6b7280'],
        ];
        return view('livewire.deals', compact('deals', 'users', 'active', 'dealStatuses', 'isAdmin'));
    }
}
