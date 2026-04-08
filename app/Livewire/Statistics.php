<?php

namespace App\Livewire;

use App\Models\User;
use App\Repositories\StatisticsRepository;
use App\Services\AgentStatisticsService;
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
    public string $selectedLocation = 'all';

    public function render()
    {
        $user = auth()->user();

        // Safe defaults so the view never crashes
        $users = collect();
        $fronterUsers = collect();
        $closerUsers = collect();
        $adminUsers = collect();
        $summary = [];
        $fronterStats = [];
        $closerStats = [];
        $adminStats = [];
        $agentSummary = [];
        $roleBreakdown = [];
        $leaderboard = [];
        $aiInsights = [];
        $canSeeAll = $user->hasRole('master_admin', 'admin');
        $isAgent = in_array($user->role, ['fronter', 'fronter_panama', 'closer', 'closer_panama']);

        try {
            $users = User::all()->keyBy('id');

            // User lists for dropdown filters
            $fronterUsers = User::whereIn('role', ['fronter', 'fronter_panama'])->orderBy('name')->get();
            $closerUsers = User::whereIn('role', ['closer', 'closer_panama'])->orderBy('name')->get();
            $adminUsers = User::whereIn('role', ['admin', 'master_admin', 'admin_limited'])->orderBy('name')->get();

            [$from, $to] = $this->computeDateRange();

            // Parse filter IDs (null = all)
            $fronterId = $this->selectedFronterId !== 'all' ? (int) $this->selectedFronterId : null;
            $closerId = $this->selectedCloserId !== 'all' ? (int) $this->selectedCloserId : null;
            $adminId = $this->selectedAdminId !== 'all' ? (int) $this->selectedAdminId : null;
            $location = $this->selectedLocation !== 'all' ? $this->selectedLocation : null;

            // Permission check — agents see only own stats
            if ($isAgent && !$canSeeAll) {
                if (in_array($user->role, ['fronter', 'fronter_panama'])) $fronterId = $user->id;
                if (in_array($user->role, ['closer', 'closer_panama'])) $closerId = $user->id;
            }

            // All stats as plain arrays
            $summary = StatisticsRepository::getOverallSummary($from, $to);
            $fronterStats = StatisticsRepository::getFronterStats($from, $to, $fronterId);
            $closerStats = StatisticsRepository::getCloserStats($from, $to, $closerId);
            $adminStats = StatisticsRepository::getAdminStats($from, $to, $adminId);

            // Filter by location if selected
            if ($location) {
                $fronterStats = array_filter($fronterStats, fn($s) => ($s['location'] ?? 'US') === $location);
                $closerStats = array_filter($closerStats, fn($s) => ($s['location'] ?? 'US') === $location);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Agent statistics system data (separate try-catch)
        try {
            $location = $this->selectedLocation !== 'all' ? $this->selectedLocation : null;
            [$from, $to] = $this->computeDateRange();
            $agentSummary = AgentStatisticsService::summary(null, $location, $from, $to);
            $roleBreakdown = AgentStatisticsService::roleBreakdown($from, $to);
            $leaderboard = AgentStatisticsService::leaderboard(null, $location, $from, $to);
            $aiInsights = AgentStatisticsService::performanceInsights($from, $to);
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.statistics', compact(
            'users', 'summary', 'fronterStats', 'closerStats', 'adminStats',
            'fronterUsers', 'closerUsers', 'adminUsers',
            'agentSummary', 'roleBreakdown', 'leaderboard', 'aiInsights',
            'canSeeAll', 'isAgent'
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
