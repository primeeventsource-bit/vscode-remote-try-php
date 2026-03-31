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

    public function selectTransfer($type, $id)
    {
        $this->selectedType = $type;
        $this->selectedId = (int) $id;
    }

    public function render()
    {
        $users = User::all()->keyBy('id');
        $transfers = collect();

        // Fronter -> Closer transfers (leads with transferred_to set to a closer)
        Lead::whereNotNull('transferred_to')->where('transferred_to', '!=', 'verification')
            ->whereNotNull('original_fronter')->get()
            ->each(function($l) use (&$transfers) {
                $transfers->push((object) [
                    'id' => $l->id,
                    'type' => 'fronter_closer',
                    'from_user' => $l->original_fronter,
                    'to_user' => is_numeric($l->transferred_to) ? (int) $l->transferred_to : null,
                    'owner_name' => $l->owner_name,
                    'resort_name' => $l->resort,
                    'fee' => null,
                    'created_at' => $l->created_at,
                    'ref_type' => 'lead',
                    'primary_phone' => $l->phone1,
                    'disposition' => $l->disposition,
                ]);
            });

        // Closer -> Admin transfers (deals with assigned_admin)
        Deal::whereNotNull('assigned_admin')->whereNotNull('closer')->get()
            ->each(function($d) use (&$transfers) {
                $transfers->push((object) [
                    'id' => $d->id,
                    'type' => 'closer_admin',
                    'from_user' => $d->closer,
                    'to_user' => $d->assigned_admin,
                    'owner_name' => $d->owner_name,
                    'resort_name' => $d->resort_name,
                    'fee' => $d->fee,
                    'created_at' => $d->timestamp ?? $d->created_at,
                    'ref_type' => 'deal',
                    'primary_phone' => $d->primary_phone,
                    'status' => $d->status,
                ]);
            });

        if ($this->filter !== 'all') $transfers = $transfers->where('type', $this->filter);
        $transfers = $transfers->sortByDesc('created_at')->values();

        $selectedTransfer = $this->selectedId
            ? $transfers->first(fn ($transfer) => $transfer->id === $this->selectedId && $transfer->ref_type === $this->selectedType)
            : null;

        return view('livewire.transfers', compact('transfers', 'users', 'selectedTransfer'));
    }
}
