<?php

namespace App\Livewire;

use App\Models\DailySalesMetric;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Daily Sales System')]
class DailySalesSystem extends Component
{
    public string $period = 'today';

    public function render()
    {
        $user = auth()->user();
        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin');

        $myProgress = SalesAnalyticsService::getDailyProgressForUser($user);
        $leaderboard = SalesAnalyticsService::getLeaderboard($this->period);

        // Team summary for admin
        $teamSummary = ['total_calls' => 0, 'total_deals' => 0, 'total_revenue' => 0, 'active_reps' => 0];
        if ($isAdmin && Schema::hasTable('daily_sales_metrics')) {
            try {
                $today = now()->toDateString();
                $metrics = DailySalesMetric::where('metric_date', $today)->get();
                $teamSummary = [
                    'total_calls' => $metrics->sum('calls_count'),
                    'total_deals' => $metrics->sum('deals_closed_count'),
                    'total_revenue' => (float) $metrics->sum('revenue_total'),
                    'active_reps' => $metrics->count(),
                ];
            } catch (\Throwable $e) {}
        }

        return view('livewire.daily-sales-system', compact('myProgress', 'leaderboard', 'teamSummary', 'isAdmin', 'isMaster'));
    }
}
