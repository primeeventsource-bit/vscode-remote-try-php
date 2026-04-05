<?php

namespace App\Livewire;

use App\Models\Lead;
use App\Models\LeadDuplicate;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Duplicate Review')]
class DuplicateReview extends Component
{
    use WithPagination;

    public string $typeFilter = 'all';
    public string $statusFilter = 'pending';
    public string $search = '';
    public int $perPage = 25;
    public array $selectedIds = [];

    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedSearch() { $this->resetPage(); }

    public function keepBoth(int $id): void
    {
        $dup = LeadDuplicate::find($id);
        if ($dup) {
            $dup->update(['review_status' => 'kept_both', 'reviewed_by' => auth()->id()]);
        }
    }

    public function deleteDuplicate(int $id): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;

        $dup = LeadDuplicate::find($id);
        if (!$dup) return;

        $duplicateLead = Lead::find($dup->duplicate_lead_id);
        if ($duplicateLead) {
            $duplicateLead->delete(); // Soft delete
        }

        $dup->update(['review_status' => 'deleted_duplicate', 'reviewed_by' => auth()->id()]);
    }

    public function ignore(int $id): void
    {
        $dup = LeadDuplicate::find($id);
        if ($dup) {
            $dup->update(['review_status' => 'ignored', 'reviewed_by' => auth()->id()]);
        }
    }

    public function bulkKeep(): void
    {
        if (empty($this->selectedIds)) return;
        LeadDuplicate::whereIn('id', $this->selectedIds)
            ->where('review_status', 'pending')
            ->update(['review_status' => 'kept_both', 'reviewed_by' => auth()->id()]);
        $this->selectedIds = [];
    }

    public function bulkIgnore(): void
    {
        if (empty($this->selectedIds)) return;
        LeadDuplicate::whereIn('id', $this->selectedIds)
            ->where('review_status', 'pending')
            ->update(['review_status' => 'ignored', 'reviewed_by' => auth()->id()]);
        $this->selectedIds = [];
    }

    public function bulkDelete(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;
        if (empty($this->selectedIds)) return;

        $dups = LeadDuplicate::whereIn('id', $this->selectedIds)->get();
        foreach ($dups as $dup) {
            $duplicateLead = Lead::find($dup->duplicate_lead_id);
            if ($duplicateLead) {
                $duplicateLead->delete();
            }
            $dup->update(['review_status' => 'deleted_duplicate', 'reviewed_by' => auth()->id()]);
        }
        $this->selectedIds = [];
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user || !$user->hasPerm('view_all_leads')) {
            abort(403);
        }

        $query = LeadDuplicate::query()
            ->with(['lead', 'duplicateLead'])
            ->orderByDesc('detected_at');

        if ($this->typeFilter !== 'all') {
            $query->where('duplicate_type', $this->typeFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('review_status', $this->statusFilter);
        }

        if ($this->search) {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('lead', fn($lq) => $lq->where('owner_name', 'like', "%{$s}%")->orWhere('phone1', 'like', "%{$s}%"))
                  ->orWhereHas('duplicateLead', fn($lq) => $lq->where('owner_name', 'like', "%{$s}%")->orWhere('phone1', 'like', "%{$s}%"));
            });
        }

        $duplicates = $query->paginate($this->perPage);

        $counts = [
            'total' => LeadDuplicate::count(),
            'exact' => LeadDuplicate::where('duplicate_type', 'exact')->count(),
            'possible' => LeadDuplicate::where('duplicate_type', 'possible')->count(),
            'pending' => LeadDuplicate::where('review_status', 'pending')->count(),
            'reviewed' => LeadDuplicate::where('review_status', '!=', 'pending')->count(),
        ];

        return view('livewire.duplicate-review', compact('duplicates', 'counts'));
    }
}
