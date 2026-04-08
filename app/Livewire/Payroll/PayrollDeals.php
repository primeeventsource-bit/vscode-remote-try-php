<?php

namespace App\Livewire\Payroll;

use App\Models\DealFinancial;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Payroll Deals')]
class PayrollDeals extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public string $disputedFilter = 'all';
    public int $perPage = 25;

    public function updatedStatusFilter() { $this->resetPage(); }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('master_admin', 'admin');

        $query = DealFinancial::with('deal')->orderByDesc('calculated_at');

        // Agents see only their own deals
        if (!$isAdmin) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('fronter', $user->id)->orWhere('closer', $user->id);
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->whereHas('deal', fn($q) => $q->where('payroll_status', $this->statusFilter));
        }
        if ($this->disputedFilter === 'disputed') $query->where('is_disputed', true);
        if ($this->disputedFilter === 'reversed') $query->where('is_reversed', true);

        $financials = $query->paginate($this->perPage);

        return view('livewire.payroll.deals', compact('financials', 'isAdmin'));
    }
}
