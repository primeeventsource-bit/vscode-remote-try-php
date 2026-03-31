<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Transfers')]
class Transfers extends Component
{
    public string $filter = 'all';
    public ?int $selectedId = null;
    public ?string $selectedType = null;

    public function selectTransfer($type, $id) { $this->selectedType = $type; $this->selectedId = $id; }

    public function render()
    {
        $users = User::all()->keyBy('id');
        $transfers = collect();

        // Fronter -> Closer transfers (leads with transferred_to set to a closer)
        Lead::whereNotNull('transferred_to')->where('transferred_to', '!=', 'verification')
            ->whereNotNull('original_fronter')->get()
            ->each(function($l) use (&$transfers) {
                $transfers->push((object)['id' => $l->id, 'type' => 'fronter_to_closer', 'from' => $l->original_fronter, 'to' => $l->transferred_to, 'name' => $l->owner_name, 'amount' => null, 'timestamp' => $l->created_at, 'ref_type' => 'lead']);
            });

        // Closer -> Admin transfers (deals with assigned_admin)
        Deal::whereNotNull('assigned_admin')->whereNotNull('closer')->get()
            ->each(function($d) use (&$transfers) {
                $transfers->push((object)['id' => $d->id, 'type' => 'closer_to_admin', 'from' => $d->closer, 'to' => $d->assigned_admin, 'name' => $d->owner_name, 'amount' => $d->fee, 'timestamp' => $d->timestamp, 'ref_type' => 'deal']);
            });

        if ($this->filter !== 'all') $transfers = $transfers->where('type', $this->filter);
        $transfers = $transfers->sortByDesc('timestamp')->values();

        $selectedDeal = ($this->selectedType === 'deal' && $this->selectedId) ? Deal::find($this->selectedId) : null;
        $selectedLead = ($this->selectedType === 'lead' && $this->selectedId) ? Lead::find($this->selectedId) : null;

        return view('livewire.transfers', compact('transfers', 'users', 'selectedDeal', 'selectedLead'));
    }
}
