<?php

namespace App\Livewire;

use App\Models\Lead;
use App\Models\LeadSweepLog;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Lead Sweep Log')]
class LeadSweepLogPage extends Component
{
    use WithPagination;

    public string $ruleFilter = 'all';
    public string $dateFilter = '30d'; // 7d | 30d | all
    public int $perPage = 50;
    public string $flash = '';

    public function updatedRuleFilter() { $this->resetPage(); }
    public function updatedDateFilter() { $this->resetPage(); }

    private function gate(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin')) abort(403);
    }

    public function mount(): void { $this->gate(); }

    public function revert(int $id): void
    {
        $this->gate();
        $row = LeadSweepLog::find($id);
        if (!$row) { $this->flash = 'Log row not found.'; return; }
        if ($row->isReverted()) { $this->flash = 'Already reverted.'; return; }
        if ($row->rule === 'conflict_skipped') { $this->flash = 'Nothing to revert — this rule never wrote data.'; return; }

        $lead = Lead::find($row->lead_id);
        if (!$lead) { $this->flash = 'Lead no longer exists.'; return; }

        DB::transaction(function () use ($row, $lead) {
            // Restore old_value onto the original field
            $lead->{$row->field_name} = $row->old_value;
            $lead->save();

            // Log the revert itself so the audit trail is complete
            LeadSweepLog::create([
                'lead_id' => $row->lead_id,
                'field_name' => $row->field_name,
                'old_value' => $row->new_value,
                'new_value' => $row->old_value,
                'rule' => 'reverted_' . $row->rule,
                'created_at' => now(),
            ]);

            // Mark original as reverted
            $row->reverted_by = auth()->id();
            $row->reverted_at = now();
            $row->save();
        });

        $this->flash = "Reverted change on lead #{$row->lead_id}.";
    }

    public function render()
    {
        $q = LeadSweepLog::query()->with('lead')->orderByDesc('id');

        if ($this->ruleFilter !== 'all') {
            $q->where('rule', $this->ruleFilter);
        }
        if ($this->dateFilter === '7d') {
            $q->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->dateFilter === '30d') {
            $q->where('created_at', '>=', now()->subDays(30));
        }

        $logs = $q->paginate($this->perPage);

        $rules = LeadSweepLog::query()->distinct()->pluck('rule')->sort()->values();

        return view('livewire.lead-sweep-log', compact('logs', 'rules'));
    }
}
