<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Verification')]
class Verification extends Component
{
    public string $tab = 'pending';
    public ?int $selectedDeal = null;
    public string $noteInput = '';

    public function selectDeal($id) { $this->selectedDeal = $this->selectedDeal === $id ? null : $id; }

    public function updateStatus($id, $status, $extra = [])
    {
        Deal::where('id', $id)->update(array_merge(['status' => $status], $extra));
    }

    public function addNote()
    {
        if (!$this->noteInput || !$this->selectedDeal) return;
        $deal = Deal::find($this->selectedDeal);
        if (!$deal) return;
        $corr = is_array($deal->correspondence) ? $deal->correspondence : json_decode($deal->correspondence ?? '[]', true);
        $corr[] = now()->format('n/j') . ' - ' . auth()->user()->name . ': ' . $this->noteInput;
        $deal->update(['correspondence' => json_encode($corr)]);
        $this->noteInput = '';
    }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('toggle_charged');
        $base = Deal::query()->orderBy('id', 'desc');
        if (!$user->hasRole('master_admin') && $isAdmin) $base->where('assigned_admin', $user->id);
        if ($user->role === 'closer') $base->where('closer', $user->id);

        $tabs = ['pending' => 'pending_admin', 'verifying' => 'in_verification', 'charged' => 'charged', 'chargeback' => 'chargeback', 'cancelled' => 'cancelled'];
        $query = clone $base;
        if ($this->tab !== 'all' && isset($tabs[$this->tab])) $query->where('status', $tabs[$this->tab]);
        $deals = $query->get();

        $counts = [];
        foreach ($tabs as $k => $v) { $counts[$k] = (clone $base)->where('status', $v)->count(); }
        $counts['all'] = (clone $base)->count();

        $users = User::all()->keyBy('id');
        $activeDeal = $this->selectedDeal ? Deal::find($this->selectedDeal) : null;
        return view('livewire.verification', compact('deals', 'users', 'counts', 'activeDeal', 'isAdmin'));
    }
}
