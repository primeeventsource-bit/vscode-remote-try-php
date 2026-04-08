<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantTransaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Finance Transactions')]
class FinanceTransactions extends Component
{
    use WithPagination;

    public string $midFilter = 'all';
    public string $statusFilter = 'all';
    public string $cardFilter = 'all';
    public int $perPage = 25;

    public function updatedMidFilter() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) abort(403);

        $transactions = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);
        $mids = collect();

        try {
            $query = MerchantTransaction::with('merchantAccount')->orderByDesc('transaction_date');

            if ($this->midFilter !== 'all') $query->where('merchant_account_id', (int) $this->midFilter);
            if ($this->statusFilter !== 'all') $query->where('transaction_status', $this->statusFilter);
            if ($this->cardFilter !== 'all') $query->where('card_brand', $this->cardFilter);

            $transactions = $query->paginate($this->perPage);
            $mids = MerchantAccount::active()->orderBy('account_name')->get();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.transactions', compact('transactions', 'mids'));
    }
}
