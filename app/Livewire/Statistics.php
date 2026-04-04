<?php

namespace App\Livewire;

use App\Models\User;
use App\Repositories\StatisticsRepository;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Statistics')]
class Statistics extends Component
{
    public string $tab = 'summary';
    public string $statsRange = 'live';

    public function render()
    {
        $users = User::all()->keyBy('id');

        [$from, $to] = $this->computeDateRange();

        // All stats as plain arrays — never stdClass, never mixed types
        $summary = StatisticsRepository::getOverallSummary($from, $to);
        $fronterStats = StatisticsRepository::getFronterStats($from, $to);
        $closerStats = StatisticsRepository::getCloserStats($from, $to);
        $adminStats = StatisticsRepository::getAdminStats($from, $to);

        return view('livewire.statistics', compact(
            'users', 'summary', 'fronterStats', 'closerStats', 'adminStats'
        ));
    }

    /**
     * Live   = all-time totals
     * Daily  = today (start of day → now)
     * Weekly = start of week → now
     * Monthly = start of month → now
     */
    private function computeDateRange(): array
    {
        $now = Carbon::now();

        return match ($this->statsRange) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [null, null], // 'live' = all time
        };
    }
}
