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
    public string $successMessage = '';
    public string $errorMessage = '';

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
        $this->successMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'account_name' => 'required|string|max:255',
            'mid_number' => 'required|string|max:50',
            'processor_name' => 'required|string|max:255',
        ]);

        try {
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
                $this->successMessage = 'Merchant account updated.';
            } else {
                $data['created_by'] = auth()->id();
                MerchantAccount::create($data);
                $this->successMessage = 'Merchant account created.';
            }

            $this->resetForm();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Failed to save: ' . $e->getMessage();
        }
    }

    public function edit(int $id)
    {
        try {
            $mid = MerchantAccount::findOrFail($id);
            $this->editingId = $mid->id;
            $this->account_name = $mid->account_name ?? $mid->name ?? '';
            $this->mid_number = $mid->mid_number ?? $mid->mid_masked ?? '';
            $this->processor_name = $mid->processor_name ?? '';
            $this->gateway_name = $mid->gateway_name ?? '';
            $this->descriptor = $mid->descriptor ?? '';
            $this->business_name = $mid->business_name ?? '';
            $this->account_status = $mid->account_status ?? 'active';
            $this->currency = $mid->currency ?? 'USD';
            $this->notes = $mid->notes ?? '';
            $this->showForm = true;
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'Failed to load account.';
        }
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
            $accounts = MerchantAccount::orderBy('id')->get()->map(function ($mid) {
                return [
                    'id' => $mid->id,
                    'account_name' => $mid->account_name ?? $mid->name ?? 'Unknown',
                    'mid_number' => $mid->mid_number ?? $mid->mid_masked ?? '--',
                    'processor_name' => $mid->processor_name ?? '--',
                    'account_status' => $mid->account_status ?? ($mid->active ? 'active' : 'inactive'),
                    'is_active' => (bool) ($mid->is_active ?? $mid->active ?? true),
                    'txn_count' => 0,
                    'approved_volume' => 0,
                    'cb_count' => 0,
                    'cb_amount' => 0,
                    'fee_total' => 0,
                    'statement_count' => 0,
                ];
            });

            // Only query finance tables if they exist
            if (\Illuminate\Support\Facades\Schema::hasTable('merchant_transactions')) {
                $accounts = $accounts->map(function ($acc) {
                    $acc['txn_count'] = MerchantTransaction::where('merchant_account_id', $acc['id'])->count();
                    $acc['approved_volume'] = (float) MerchantTransaction::where('merchant_account_id', $acc['id'])->whereIn('transaction_status', ['approved', 'settled'])->sum('amount');
                    return $acc;
                });
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('merchant_chargebacks')) {
                $accounts = $accounts->map(function ($acc) {
                    $acc['cb_count'] = MerchantChargeback::where('merchant_account_id', $acc['id'])->count();
                    $acc['cb_amount'] = (float) MerchantChargeback::where('merchant_account_id', $acc['id'])->sum('amount');
                    return $acc;
                });
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('merchant_financial_entries')) {
                $accounts = $accounts->map(function ($acc) {
                    $acc['fee_total'] = (float) MerchantFinancialEntry::where('merchant_account_id', $acc['id'])->where('entry_type', 'fee')->sum('amount');
                    return $acc;
                });
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('merchant_statement_uploads')) {
                $accounts = $accounts->map(function ($acc) {
                    $acc['statement_count'] = \App\Models\MerchantStatementUpload::where('merchant_account_id', $acc['id'])->count();
                    return $acc;
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.finance.merchant-accounts', compact('accounts'));
    }
}
