<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pipeline')]
class Pipeline extends Component
{
    use WithPagination;

    public int $perPage = 25;

    public function updatedPerPage() { $this->resetPage(); }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $users = User::all()->keyBy('id');

        $pendingDeals = $isAdmin
            ? Deal::whereIn('status', ['pending_admin', 'in_verification'])
                ->when(!$user->hasRole('master_admin'), fn($q) => $q->where('assigned_admin', $user->id))
                ->orderBy('id', 'desc')->paginate($this->perPage, ['*'], 'dealsPage')
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);

        $callbackLeads = !$isAdmin
            ? Lead::where('disposition', 'Callback')->where('assigned_to', $user->id)->orderBy('callback_date')->paginate($this->perPage, ['*'], 'leadsPage')
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);

        return view('livewire.pipeline', compact('pendingDeals', 'callbackLeads', 'users', 'isAdmin'));
    }
}
