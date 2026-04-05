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
#[Title('Transfers')]
class Transfers extends Component
{
    use WithPagination;

    public string $filter = 'all';
    public int $perPage = 25;
    public ?int $selectedId = null;
    public ?string $selectedType = null;

    public function updatedFilter() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function selectTransfer($type, $id)
    {
        $this->selectedType = $type;
        $this->selectedId = (int) $id;
    }

    public function render()
    {
        $users = User::all()->keyBy('id');

        // Build paginated transfers using DB-level queries instead of loading all into memory
        $leadTransfers = Lead::whereNotNull('transferred_to')
            ->where('transferred_to', '!=', 'verification')
            ->whereNotNull('original_fronter')
            ->select([
                'id', 'original_fronter as from_user', 'transferred_to as to_user',
                'owner_name', 'resort as resort_name', 'phone1 as primary_phone',
                'disposition', 'created_at',
            ])
            ->selectRaw("'fronter_closer' as type, 'lead' as ref_type, NULL as fee, NULL as status");

        $dealTransfers = Deal::whereNotNull('assigned_admin')
            ->whereNotNull('closer')
            ->select([
                'id', 'closer as from_user', 'assigned_admin as to_user',
                'owner_name', 'resort_name', 'primary_phone',
            ])
            ->selectRaw("NULL as disposition, COALESCE(timestamp, created_at) as created_at, 'closer_admin' as type, 'deal' as ref_type, fee, status");

        if ($this->filter === 'fronter_closer') {
            $query = $leadTransfers;
        } elseif ($this->filter === 'closer_admin') {
            $query = $dealTransfers;
        } else {
            $query = $leadTransfers->union($dealTransfers);
        }

        $transfers = $query->orderByDesc('created_at')->paginate($this->perPage);

        $selectedTransfer = $this->selectedId
            ? $transfers->first(fn ($transfer) => $transfer->id === $this->selectedId && $transfer->ref_type === $this->selectedType)
            : null;

        return view('livewire.transfers', compact('transfers', 'users', 'selectedTransfer'));
    }
}
