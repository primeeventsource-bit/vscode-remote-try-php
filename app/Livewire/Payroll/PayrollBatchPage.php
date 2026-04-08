<?php

namespace App\Livewire\Payroll;

use App\Models\PayrollBatchV2;
use App\Services\Payroll\PayrollBatchBuilder;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payroll Batches')]
class PayrollBatchPage extends Component
{
    public string $tab = 'list'; // list, detail
    public ?int $selectedBatchId = null;
    public string $batchTab = 'summary'; // summary, deals, employees

    public function buildWeeklyBatch()
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $batch = PayrollBatchBuilder::buildWeeklyBatch($start, $end);
        $this->selectedBatchId = $batch->id;
        $this->tab = 'detail';
        session()->flash('payroll_success', "Batch created: {$batch->batch_name} — {$batch->batchDeals->count()} deals included.");
    }

    public function selectBatch(int $id)
    {
        $this->selectedBatchId = $id;
        $this->tab = 'detail';
        $this->batchTab = 'summary';
    }

    public function backToList()
    {
        $this->tab = 'list';
        $this->selectedBatchId = null;
    }

    public function approveBatch()
    {
        $batch = PayrollBatchV2::findOrFail($this->selectedBatchId);
        PayrollBatchBuilder::approveBatch($batch);
        session()->flash('payroll_success', 'Batch approved.');
    }

    public function lockBatch()
    {
        if (!auth()->user()->hasRole('master_admin')) {
            session()->flash('payroll_error', 'Only Master Admin can lock batches.');
            return;
        }
        $batch = PayrollBatchV2::findOrFail($this->selectedBatchId);
        PayrollBatchBuilder::lockBatch($batch);
        session()->flash('payroll_success', 'Batch locked. All included deal financials are now locked.');
    }

    public function markPaid()
    {
        if (!auth()->user()->hasRole('master_admin')) {
            session()->flash('payroll_error', 'Only Master Admin can mark batches as paid.');
            return;
        }
        $batch = PayrollBatchV2::findOrFail($this->selectedBatchId);
        PayrollBatchBuilder::markPaid($batch);
        session()->flash('payroll_success', 'Batch marked as paid.');
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user->hasRole('master_admin', 'admin')) abort(403);

        $batches = collect();
        $selectedBatch = null;
        $batchItems = collect();
        $batchDeals = collect();

        try {
            $batches = PayrollBatchV2::orderByDesc('period_start')->limit(25)->get();

            if ($this->selectedBatchId) {
                $selectedBatch = PayrollBatchV2::with(['items.user', 'batchDeals.deal', 'batchDeals.dealFinancial'])->find($this->selectedBatchId);
                if ($selectedBatch) {
                    $batchItems = $selectedBatch->items;
                    $batchDeals = $selectedBatch->batchDeals;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $isMaster = $user->hasRole('master_admin');

        return view('livewire.payroll.batch', compact('batches', 'selectedBatch', 'batchItems', 'batchDeals', 'isMaster'));
    }
}
