<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantChargeback;
use App\Models\MerchantChargebackEvent;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Finance Chargebacks')]
class FinanceChargebacks extends Component
{
    use WithPagination;

    public string $midFilter = 'all';
    public string $statusFilter = 'all';
    public string $tab = 'all'; // all, open, due_soon, won, lost
    public int $perPage = 25;

    public function updatedTab() { $this->resetPage(); }
    public function updatedMidFilter() { $this->resetPage(); }

    public function updateStatus(int $id, string $status, ?string $outcome = null)
    {
        $cb = MerchantChargeback::find($id);
        if (!$cb) return;

        $old = $cb->internal_status;
        $data = ['internal_status' => $status];
        if ($outcome) {
            $data['outcome'] = $outcome;
            $data['resolved_at'] = now();
        }

        $cb->update($data);

        MerchantChargebackEvent::create([
            'merchant_chargeback_id' => $cb->id,
            'event_type' => 'status_changed',
            'old_status' => $old,
            'new_status' => $status,
        ]);

        session()->flash('finance_success', 'Chargeback status updated.');
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) abort(403);

        $chargebacks = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);
        $mids = collect();
        $counts = ['all' => 0, 'open' => 0, 'due_soon' => 0, 'won' => 0, 'lost' => 0];

        try {
            $query = MerchantChargeback::with('merchantAccount')->orderByDesc('opened_at');

            if ($this->midFilter !== 'all') $query->where('merchant_account_id', (int) $this->midFilter);
            if ($this->statusFilter !== 'all') $query->where('internal_status', $this->statusFilter);

            if ($this->tab === 'open') $query->open();
            if ($this->tab === 'due_soon') $query->dueSoon();
            if ($this->tab === 'won') $query->won();
            if ($this->tab === 'lost') $query->lost();

            $chargebacks = $query->paginate($this->perPage);
            $mids = MerchantAccount::active()->orderBy('account_name')->get();

            // Tab counts
            $countBase = MerchantChargeback::query();
            if ($this->midFilter !== 'all') $countBase->where('merchant_account_id', (int) $this->midFilter);
            $counts = [
                'all' => (clone $countBase)->count(),
                'open' => (clone $countBase)->open()->count(),
                'due_soon' => (clone $countBase)->dueSoon()->count(),
                'won' => (clone $countBase)->won()->count(),
                'lost' => (clone $countBase)->lost()->count(),
            ];
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.chargebacks', compact('chargebacks', 'mids', 'counts'));
    }
}
