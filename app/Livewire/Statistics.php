<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Statistics')]
class Statistics extends Component
{
    public string $tab = 'revenue';

    public function render()
    {
        $deals = Deal::all();
        $leads = Lead::all();
        $users = User::all()->keyBy('id');
        $charged = $deals->where('charged', 'yes');
        $cb = $deals->where('charged_back', 'yes');
        $now = Carbon::now();

        // Period data
        $periods = [
            'Week' => $now->copy()->subWeek(), 'Month' => $now->copy()->subMonth(),
            'Quarter' => $now->copy()->subMonths(3), 'Year' => $now->copy()->subYear(),
        ];
        $periodData = collect($periods)->map(fn($from, $label) => [
            'label' => $label,
            'chargedRev' => $charged->filter(fn($d) => Carbon::parse($d->charged_date ?: $d->timestamp)->gte($from))->sum('fee'),
            'chargedCt' => $charged->filter(fn($d) => Carbon::parse($d->charged_date ?: $d->timestamp)->gte($from))->count(),
            'cbRev' => $cb->filter(fn($d) => Carbon::parse($d->charged_date ?: $d->timestamp)->gte($from))->sum('fee'),
            'cbCt' => $cb->filter(fn($d) => Carbon::parse($d->charged_date ?: $d->timestamp)->gte($from))->count(),
        ]);

        // Fronter stats
        $fronterStats = User::where('role', 'fronter')->get()->map(function($f) use ($leads, $deals) {
            $total = $leads->where('original_fronter', $f->id)->count();
            $transferred = $leads->where('original_fronter', $f->id)->where('disposition', 'Transferred to Closer')->count();
            $fDeals = $deals->where('fronter', $f->id);
            return (object)['user' => $f, 'total' => $total, 'transferred' => $transferred, 'charged' => $fDeals->where('charged', 'yes')->count(), 'cb' => $fDeals->where('charged_back', 'yes')->count(), 'pct' => $total > 0 ? round($transferred / $total * 100, 1) : 0];
        });

        // Closer stats
        $closerStats = User::where('role', 'closer')->get()->map(function($c) use ($leads, $deals) {
            $received = $leads->where('transferred_to', $c->id)->count();
            $cd = $deals->where('closer', $c->id);
            $chargedCt = $cd->where('charged', 'yes')->count();
            $cbCt = $cd->where('charged_back', 'yes')->count();
            $rev = $cd->sum('fee');
            return (object)['user' => $c, 'received' => $received, 'deals' => $cd->count(), 'charged' => $chargedCt, 'cb' => $cbCt, 'rev' => $rev, 'closePct' => $received > 0 ? round($cd->count() / $received * 100, 1) : 0, 'cbPct' => $cd->count() > 0 ? round($cbCt / $cd->count() * 100, 1) : 0];
        });

        return view('livewire.statistics', compact('deals', 'users', 'periodData', 'fronterStats', 'closerStats', 'charged', 'cb'));
    }
}
