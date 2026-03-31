<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Pipeline')]
class Pipeline extends Component
{
    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $users = User::all()->keyBy('id');

        $pendingDeals = $isAdmin
            ? Deal::whereIn('status', ['pending_admin', 'in_verification'])
                ->when(!$user->hasRole('master_admin'), fn($q) => $q->where('assigned_admin', $user->id))
                ->orderBy('id', 'desc')->get()
            : collect();

        $callbackLeads = !$isAdmin
            ? Lead::where('disposition', 'Callback')->where('assigned_to', $user->id)->orderBy('callback_date')->get()
            : collect();

        return view('livewire.pipeline', compact('pendingDeals', 'callbackLeads', 'users', 'isAdmin'));
    }
}
