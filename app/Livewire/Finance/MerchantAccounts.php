<?php

namespace App\Livewire\Finance;

use App\Models\MerchantAccount;
use App\Models\MerchantChargeback;
use App\Models\MerchantFinancialEntry;
use App\Models\MerchantTransaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Merchant Accounts')]
class MerchantAccounts extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    // Form fields
    public string $account_name = '';
    public string $mid_number = '';
    public string $processor_name = '';
    public string $gateway_name = '';
    public string $descriptor = '';
    public string $business_name = '';
    public string $account_status = 'active';
    public string $currency = 'USD';
    public string $notes = '';

    public function save()
    {
        $this->validate([
            'account_name' => 'required|string|max:255',
            'mid_number' => 'required|string|max:50|unique:merchant_accounts,mid_number,' . ($this->editingId ?? 'NULL'),
            'processor_name' => 'required|string|max:255',
        ]);

        $data = [
            'account_name' => $this->account_name,
            'mid_number' => $this->mid_number,
            'processor_name' => $this->processor_name,
            'gateway_name' => $this->gateway_name ?: null,
            'descriptor' => $this->descriptor ?: null,
            'business_name' => $this->business_name ?: null,
            'account_status' => $this->account_status,
            'currency' => $this->currency ?: 'USD',
            'notes' => $this->notes ?: null,
            'is_active' => $this->account_status === 'active',
            'updated_by' => auth()->id(),
        ];

        if ($this->editingId) {
            MerchantAccount::where('id', $this->editingId)->update($data);
            session()->flash('finance_success', 'Merchant account updated.');
        } else {
            $data['created_by'] = auth()->id();
            MerchantAccount::create($data);
            session()->flash('finance_success', 'Merchant account created.');
        }

        $this->resetForm();
    }

    public function edit(int $id)
    {
        $mid = MerchantAccount::findOrFail($id);
        $this->editingId = $mid->id;
        $this->account_name = $mid->account_name;
        $this->mid_number = $mid->mid_number;
        $this->processor_name = $mid->processor_name;
        $this->gateway_name = $mid->gateway_name ?? '';
        $this->descriptor = $mid->descriptor ?? '';
        $this->business_name = $mid->business_name ?? '';
        $this->account_status = $mid->account_status;
        $this->currency = $mid->currency ?? 'USD';
        $this->notes = $mid->notes ?? '';
        $this->showForm = true;
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->account_name = '';
        $this->mid_number = '';
        $this->processor_name = '';
        $this->gateway_name = '';
        $this->descriptor = '';
        $this->business_name = '';
        $this->account_status = 'active';
        $this->currency = 'USD';
        $this->notes = '';
        $this->showForm = false;
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin') && !$user->hasPerm('view_finance')) abort(403);

        $accounts = collect();

        try {
            $accounts = MerchantAccount::orderBy('account_name')->get()->map(function ($mid) {
                return [
                    'id' => $mid->id,
                    'account_name' => $mid->account_name,
                    'mid_number' => $mid->mid_number,
                    'processor_name' => $mid->processor_name,
                    'account_status' => $mid->account_status,
                    'is_active' => $mid->is_active,
                    'txn_count' => MerchantTransaction::where('merchant_account_id', $mid->id)->count(),
                    'approved_volume' => (float) MerchantTransaction::where('merchant_account_id', $mid->id)->whereIn('transaction_status', ['approved', 'settled'])->sum('amount'),
                    'cb_count' => MerchantChargeback::where('merchant_account_id', $mid->id)->count(),
                    'cb_amount' => (float) MerchantChargeback::where('merchant_account_id', $mid->id)->sum('amount'),
                    'fee_total' => (float) MerchantFinancialEntry::where('merchant_account_id', $mid->id)->where('entry_type', 'fee')->sum('amount'),
                    'statement_count' => $mid->statementUploads()->count(),
                ];
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.merchant-accounts', compact('accounts'));
    }
}
