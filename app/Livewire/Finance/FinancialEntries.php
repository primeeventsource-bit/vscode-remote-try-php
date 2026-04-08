<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantFinancialEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Financial Entries')]
class FinancialEntries extends Component
{
    use WithPagination;

    public string $midFilter = 'all';
    public string $typeFilter = 'all';
    public int $perPage = 25;

    public function updatedMidFilter() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) abort(403);

        $query = MerchantFinancialEntry::with('merchantAccount')->orderByDesc('entry_date');

        if ($this->midFilter !== 'all') $query->where('merchant_account_id', (int) $this->midFilter);
        if ($this->typeFilter !== 'all') $query->where('entry_type', $this->typeFilter);

        $entries = $query->paginate($this->perPage);
        $mids = MerchantAccount::active()->orderBy('account_name')->get();

        // Totals by type
        $totals = MerchantFinancialEntry::selectRaw('entry_type, SUM(amount) as total, COUNT(*) as cnt')
            ->when($this->midFilter !== 'all', fn($q) => $q->where('merchant_account_id', (int) $this->midFilter))
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type')
            ->toArray();

        return view('livewire.finance.financial-entries', compact('entries', 'mids', 'totals'));
    }
}
