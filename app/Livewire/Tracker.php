<?php
namespace App\Livewire;

use App\Models\Deal;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tracker')]
class Tracker extends Component
{
    public int $weekOffset = 0;

    public function prevWeek() { $this->weekOffset--; }
    public function nextWeek() { $this->weekOffset++; }
    public function thisWeek() { $this->weekOffset = 0; }

    public function render()
    {
        $startDate = Carbon::now()->startOfWeek()->addWeeks($this->weekOffset);
        $days = collect(range(0, 6))->map(fn($i) => $startDate->copy()->addDays($i));
        $weekLabel = $days->first()->format('M j') . ' - ' . $days->last()->format('M j, Y');
        $prevStart = $startDate->copy()->subWeek();

        $charged = Deal::where('charged', 'yes')->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'))->get();
        $fronters = User::where('role', 'fronter')->get();
        $closers = User::where('role', 'closer')->get();

        $isSameDay = function($dateStr, $day) {
            if (!$dateStr) return false;
            try { return Carbon::parse($dateStr)->format('Y-m-d') === $day->format('Y-m-d'); } catch (\Exception $e) { return false; }
        };

        $userDayData = [];
        $weekTotals = [];
        $prevTotals = [];

        foreach (['fronter' => $fronters, 'closer' => $closers] as $role => $roleUsers) {
            foreach ($roleUsers as $u) {
                $field = $role;
                foreach ($days as $i => $day) {
                    $dd = $charged->filter(fn($d) => $isSameDay($d->charged_date ?: $d->timestamp, $day) && $d->$field == $u->id);
                    $userDayData[$u->id][$i] = ['deals' => $dd, 'rev' => $dd->sum('fee'), 'count' => $dd->count()];
                }
                $weekD = $charged->filter(fn($d) => $d->$field == $u->id && ($dt = $d->charged_date ?: $d->timestamp) && Carbon::parse($dt)->between($days->first(), $days->last()->endOfDay()));
                $weekTotals[$u->id] = ['rev' => $weekD->sum('fee'), 'count' => $weekD->count()];
                $prevD = $charged->filter(fn($d) => $d->$field == $u->id && ($dt = $d->charged_date ?: $d->timestamp) && Carbon::parse($dt)->between($prevStart, $prevStart->copy()->addDays(6)->endOfDay()));
                $prevTotals[$u->id] = ['rev' => $prevD->sum('fee'), 'count' => $prevD->count()];
            }
        }

        $dayTotals = $days->map(fn($day, $i) => [
            'rev' => $charged->filter(fn($d) => $isSameDay($d->charged_date ?: $d->timestamp, $day))->sum('fee'),
            'count' => $charged->filter(fn($d) => $isSameDay($d->charged_date ?: $d->timestamp, $day))->count(),
        ]);

        return view('livewire.tracker', compact('days', 'weekLabel', 'fronters', 'closers', 'userDayData', 'dayTotals', 'weekTotals', 'prevTotals'));
    }
}
