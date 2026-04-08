<?php

namespace App\Livewire\Payroll;

use App\Models\Deal;
use App\Models\DealFinancial;
use App\Models\FinanceAudit;
use App\Services\Payroll\DealPayrollSyncService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Deal Financial Detail')]
class DealFinancialDetail extends Component
{
    public int $dealId;
    public float $adjustmentAmount = 0;
    public string $adjustmentReason = '';
    public string $reverseReason = '';

    public function mount(int $dealId)
    {
        $this->dealId = $dealId;
    }

    public function recalculate()
    {
        $deal = Deal::findOrFail($this->dealId);
        $financial = DealFinancial::where('deal_id', $deal->id)->first();

        if ($financial && $financial->is_locked && !auth()->user()->hasRole('master_admin')) {
            session()->flash('payroll_error', 'Cannot recalculate locked deal. Master Admin override required.');
            return;
        }

        DealPayrollSyncService::syncForDeal($deal);
        session()->flash('payroll_success', 'Deal payroll recalculated.');
    }

    public function lockDeal()
    {
        $deal = Deal::findOrFail($this->dealId);
        DealPayrollSyncService::syncForDeal($deal, lock: true);
        session()->flash('payroll_success', 'Deal payroll locked.');
    }

    public function addAdjustment()
    {
        $this->validate([
            'adjustmentAmount' => 'required|numeric',
            'adjustmentReason' => 'required|string|min:3',
        ]);

        $deal = Deal::findOrFail($this->dealId);
        DealPayrollSyncService::addAdjustment($deal, $this->adjustmentAmount, $this->adjustmentReason);
        $this->adjustmentAmount = 0;
        $this->adjustmentReason = '';
        session()->flash('payroll_success', 'Adjustment applied.');
    }

    public function markDisputed()
    {
        $deal = Deal::findOrFail($this->dealId);
        DealPayrollSyncService::markDisputed($deal);
        session()->flash('payroll_success', 'Deal marked as disputed.');
    }

    public function reverseDeal()
    {
        if (!$this->reverseReason) {
            session()->flash('payroll_error', 'Reversal reason required.');
            return;
        }

        $deal = Deal::findOrFail($this->dealId);
        DealPayrollSyncService::reverseDeal($deal, $this->reverseReason);
        $this->reverseReason = '';
        session()->flash('payroll_success', 'Deal payroll reversed.');
    }

    public function render()
    {
        $user = auth()->user();
        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin');

        // Agents can only see their own deals
        $deal = Deal::findOrFail($this->dealId);
        if (!$isAdmin && $deal->fronter != $user->id && $deal->closer != $user->id) {
            abort(403);
        }

        $financial = DealFinancial::where('deal_id', $this->dealId)->first();
        $audits = FinanceAudit::where('auditable_type', 'DealFinancial')
            ->where('auditable_id', $financial?->id ?? 0)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $users = \App\Models\User::all()->keyBy('id');

        return view('livewire.payroll.deal-financial-detail', compact('deal', 'financial', 'audits', 'users', 'isMaster', 'isAdmin'));
    }
}
