<?php

namespace App\Livewire;

use App\Models\LeadImportBatch;
use App\Models\LeadImportFailure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Lead Imports')]
class LeadImports extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public int $perPage = 25;
    public ?int $detailBatchId = null;
    public string $failureFilter = 'all';

    public function updatedStatusFilter() { $this->resetPage(); }

    public function viewDetails(int $id): void
    {
        $this->detailBatchId = $this->detailBatchId === $id ? null : $id;
        $this->failureFilter = 'all';
    }

    public function retryBatch(int $id): void
    {
        $batch = LeadImportBatch::find($id);
        if (!$batch || $batch->status !== 'failed') return;

        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;

        // Get failed rows and re-queue
        $failures = LeadImportFailure::where('lead_import_batch_id', $id)
            ->where('failure_type', 'exception')
            ->whereNotNull('raw_row')
            ->get();

        if ($failures->isEmpty()) return;

        $rows = $failures->pluck('raw_row')->toArray();

        $batch->update([
            'status' => 'pending',
            'error_message' => null,
            'failed_rows' => 0,
        ]);

        \App\Jobs\ProcessLeadImportChunk::dispatch(
            $batch->id,
            $rows,
            1,
            true
        );

        // Clean up the old failures
        LeadImportFailure::where('lead_import_batch_id', $id)
            ->where('failure_type', 'exception')
            ->delete();
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user || !$user->hasPerm('view_all_leads')) {
            abort(403);
        }

        $query = LeadImportBatch::query()->orderByDesc('id');

        if (!$user->hasRole('master_admin', 'admin')) {
            $query->where('user_id', $user->id);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $imports = $query->paginate($this->perPage);

        // Detail view data
        $detailBatch = null;
        $failures = collect();
        if ($this->detailBatchId) {
            $detailBatch = LeadImportBatch::with('user')->find($this->detailBatchId);

            $failQuery = LeadImportFailure::where('lead_import_batch_id', $this->detailBatchId)
                ->orderBy('row_number');

            if ($this->failureFilter !== 'all') {
                $failQuery->where('failure_type', $this->failureFilter);
            }

            $failures = $failQuery->limit(100)->get();
        }

        return view('livewire.lead-imports', compact('imports', 'detailBatch', 'failures'));
    }
}
