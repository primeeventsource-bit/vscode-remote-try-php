<?php

namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $isCloser = $user->role === 'closer';

        $totalLeads = Lead::count();
        $assignedLeads = Lead::whereNotNull('assigned_to')->count();

        $deals = Deal::all();
        $charged = $deals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $chargebacks = $deals->where('charged_back', 'yes');
        $pending = $deals->where('charged', '!=', 'yes')->where('status', '!=', 'cancelled');
        $cancelled = $deals->where('status', 'cancelled');

        $totalRev = $charged->sum('fee');
        $cbRev = $chargebacks->sum('fee');
        $pendRev = $pending->sum('fee');

        // This week (Mon-Sun)
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $weekDeals = $deals->filter(fn($d) => $d->timestamp && \Carbon\Carbon::parse($d->timestamp)->gte($weekStart));
        $weekCharged = $weekDeals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $weekRev = $weekCharged->sum('fee');

        $closers = User::where('role', 'closer')->get()->map(function ($u) use ($deals) {
            $d = $deals->where('closer', $u->id)->where('charged', 'yes');
            return ['user' => $u, 'count' => $d->count(), 'rev' => $d->sum('fee')];
        })->sortByDesc('rev');

        $recentDeals = $deals->sortByDesc('id')->take(5);

        return view('livewire.dashboard', compact(
            'totalLeads', 'assignedLeads', 'deals', 'charged', 'chargebacks',
            'pending', 'cancelled', 'totalRev', 'cbRev', 'pendRev',
            'weekDeals', 'weekCharged', 'weekRev', 'closers', 'recentDeals',
            'isCloser'
        ));
    }
}
