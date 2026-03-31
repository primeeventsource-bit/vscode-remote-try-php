<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Clients')]
class Clients extends Component
{
    public string $search = '';
    public string $statusTab = 'all';
    public ?int $selectedClient = null;

    public function selectClient($id) { $this->selectedClient = $this->selectedClient === $id ? null : $id; }

    public function updateStatus($id, $status, $extra = [])
    {
        Deal::where('id', $id)->update(array_merge(['status' => $status], $extra));
    }

    public function render()
    {
        $query = Deal::whereIn('status', ['charged', 'chargeback', 'chargeback_won', 'chargeback_lost'])->orderBy('id', 'desc');
        if ($this->statusTab !== 'all') $query->where('status', $this->statusTab);
        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q->where('owner_name', 'like', "%$s%")->orWhere('resort_name', 'like', "%$s%"));
        }
        $clients = $query->get();
        $totalRev = Deal::whereIn('status', ['charged', 'chargeback_won'])->sum('fee');
        $cbRev = Deal::whereIn('status', ['chargeback', 'chargeback_lost'])->sum('fee');
        $users = User::all()->keyBy('id');
        $active = $this->selectedClient ? Deal::find($this->selectedClient) : null;
        return view('livewire.clients', compact('clients', 'users', 'active', 'totalRev', 'cbRev'));
    }
}
