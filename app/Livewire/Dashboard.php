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
        $user     = auth()->user();
        $isCloser = $user->role === 'closer';
        $isAdmin  = $user->hasPerm('view_all_leads');
        $isMaster = $user->role === 'master_admin';

        $totalLeads    = Lead::count();
        $assignedLeads = Lead::whereNotNull('assigned_to')->count();

        $deals      = Deal::all();
        $charged    = $deals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $chargebacks = $deals->where('charged_back', 'yes');
        $pending    = $deals->where('charged', '!=', 'yes')->where('status', '!=', 'cancelled');
        $cancelled  = $deals->where('status', 'cancelled');

        $totalRev = $charged->sum('fee');
        $cbRev    = $chargebacks->sum('fee');
        $pendRev  = $pending->sum('fee');

        // This week (Mon–Sun)
        $now       = now();
        $weekStart = $now->copy()->startOfWeek();
        $weekDeals   = $deals->filter(fn($d) => $d->timestamp && \Carbon\Carbon::parse($d->timestamp)->gte($weekStart));
        $weekCharged = $weekDeals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $weekRev     = $weekCharged->sum('fee');

        // Closer-specific stats
        $myDeals        = $deals->where('closer', $user->id);
        $myCharged      = $myDeals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $myPending      = $myDeals->where('charged', '!=', 'yes')->where('status', '!=', 'cancelled');
        $myChargebacks  = $myDeals->where('charged_back', 'yes');
        $myRevTotal     = (float) $myCharged->sum('fee');
        $myWeekDeals    = $myDeals->filter(fn($d) => $d->timestamp && \Carbon\Carbon::parse($d->timestamp)->gte($weekStart));
        $myWeekCharged  = $myWeekDeals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $myWeekRev      = (float) $myWeekCharged->sum('fee');

        // Monthly performance – last 6 months
        $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();

            $source        = $isCloser ? $myDeals : $deals;
            $monthCharged  = $source->filter(fn($d) =>
                $d->timestamp
                && \Carbon\Carbon::parse($d->timestamp)->gte($start)
                && \Carbon\Carbon::parse($d->timestamp)->lte($end)
                && $d->charged === 'yes'
                && $d->charged_back !== 'yes'
            );

            $monthlyData->push([
                'label' => $month->format('M'),
                'rev'   => (float) $monthCharged->sum('fee'),
                'count' => $monthCharged->count(),
            ]);
        }

        $closers = User::where('role', 'closer')->get()->map(function ($u) use ($deals) {
            $d = $deals->where('closer', $u->id)->where('charged', 'yes');
            return ['user' => $u, 'count' => $d->count(), 'rev' => $d->sum('fee')];
        })->sortByDesc('rev');

        $recentDeals = $deals->sortByDesc('id')->take(5);

        return view('livewire.dashboard', compact(
            'totalLeads', 'assignedLeads', 'deals', 'charged', 'chargebacks',
            'pending', 'cancelled', 'totalRev', 'cbRev', 'pendRev',
            'weekDeals', 'weekCharged', 'weekRev', 'closers', 'recentDeals',
            'isCloser', 'isAdmin', 'isMaster', 'monthlyData',
            'myDeals', 'myCharged', 'myPending', 'myChargebacks',
            'myRevTotal', 'myWeekDeals', 'myWeekCharged', 'myWeekRev'
        ));
    }
}
