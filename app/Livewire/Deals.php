<?php
namespace App\Livewire;

use App\Livewire\Concerns\SendsTransferDm;
use App\Models\Deal;
use App\Models\User;
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
            Deal::where('id', $this->dealForm['id'])->update(collect($this->dealForm)->except('id')->toArray());
        } else {
            Deal::create($this->newDeal ?: $this->dealForm);
        }
        $this->showNewDeal = false;
        $this->showModal = false;
        $this->resetForm();
    }

    public function editDeal($id)
    {
        $d = Deal::find($id);
        if ($d) { $this->dealForm = $d->toArray(); $this->showModal = true; }
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
