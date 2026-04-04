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
    public string $selectedFronterId = 'all';
    public string $selectedCloserId = 'all';
    public string $selectedAdminId = 'all';

    public function render()
    {
        $users = User::all()->keyBy('id');

        // User lists for dropdown filters
        $fronterUsers = User::whereIn('role', ['fronter', 'fronter_panama'])->orderBy('name')->get();
        $closerUsers = User::where('role', 'closer')->orderBy('name')->get();
        $adminUsers = User::whereIn('role', ['admin', 'master_admin', 'admin_limited'])->orderBy('name')->get();

        [$from, $to] = $this->computeDateRange();

        // Parse filter IDs (null = all)
        $fronterId = $this->selectedFronterId !== 'all' ? (int) $this->selectedFronterId : null;
        $closerId = $this->selectedCloserId !== 'all' ? (int) $this->selectedCloserId : null;
        $adminId = $this->selectedAdminId !== 'all' ? (int) $this->selectedAdminId : null;

        // All stats as plain arrays
        $summary = StatisticsRepository::getOverallSummary($from, $to);
        $fronterStats = StatisticsRepository::getFronterStats($from, $to, $fronterId);
        $closerStats = StatisticsRepository::getCloserStats($from, $to, $closerId);
        $adminStats = StatisticsRepository::getAdminStats($from, $to, $adminId);

        return view('livewire.statistics', compact(
            'users', 'summary', 'fronterStats', 'closerStats', 'adminStats',
            'fronterUsers', 'closerUsers', 'adminUsers'
        ));
    }

    private function computeDateRange(): array
    {
        $now = Carbon::now();

        return match ($this->statsRange) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [null, null],
        };
    }
}
