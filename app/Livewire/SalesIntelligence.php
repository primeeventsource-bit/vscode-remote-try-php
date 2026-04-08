<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardFilterData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sales Intelligence')]
class SalesIntelligence extends Component
{
    public string $dateRange = '30d';
    public string $ownerFilter = 'all';

    public function render()
    {
        $user = auth()->user();
        $filters = new DashboardFilterData(
            dateRange: $this->dateRange,
            ownerId: $this->ownerFilter !== 'all' ? (int) $this->ownerFilter : null,
        );

        // Wrap full payload in try-catch to prevent 500s from missing tables/services
        $d = [
            'summary_cards' => [],
            'priority_alerts' => ['title' => 'AI Priority Alerts', 'subtitle' => '', 'items' => [], 'meta' => ['empty' => true]],
            'charts' => ['deal_probability' => null, 'pipeline_risk' => null],
            'tables' => ['at_risk_deals' => null, 'hottest_leads' => null, 'followup_queue' => null],
            'widgets' => ['rep_coaching_watchlist' => null, 'top_mistakes' => null, 'recent_score_changes' => null, 'ai_recommendations' => null, 'upcoming_revenue' => null, 'agent_leaderboard' => null, 'agent_performance_alerts' => null],
        ];
        $meta = [];

        try {
            $payload = DashboardDataService::getFullPayload($user, $filters);
            $d = $payload['data'];
            $meta = $payload['meta'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return view('livewire.sales-intelligence', [
            'user'                => $user,
            'isMaster'            => $user->hasRole('master_admin'),
            'isAdmin'             => $user->hasRole('master_admin', 'admin', 'admin_limited'),
            'isFronter'           => in_array($user->role, ['fronter', 'fronter_panama']),
            'isCloser'            => in_array($user->role, ['closer', 'closer_panama']),
            'users'               => $user->hasRole('master_admin', 'admin') ? User::orderBy('name')->get() : collect(),
            'summaryCards'        => $d['summary_cards'] ?? [],
            'priorityAlerts'      => $d['priority_alerts'] ?? [],
            'dealProbability'     => $d['charts']['deal_probability'] ?? null,
            'pipelineRisk'        => $d['charts']['pipeline_risk'] ?? null,
            'atRiskDeals'         => $d['tables']['at_risk_deals'] ?? null,
            'hottestLeads'        => $d['tables']['hottest_leads'] ?? null,
            'followupQueue'       => $d['tables']['followup_queue'] ?? null,
            'coachingWatchlist'   => $d['widgets']['rep_coaching_watchlist'] ?? null,
            'topMistakes'         => $d['widgets']['top_mistakes'] ?? null,
            'recentScoreChanges'  => $d['widgets']['recent_score_changes'] ?? null,
            'aiRecommendations'   => $d['widgets']['ai_recommendations'] ?? null,
            'upcomingRevenue'     => $d['widgets']['upcoming_revenue'] ?? null,
            'agentLeaderboard'    => $d['widgets']['agent_leaderboard'] ?? null,
            'agentPerfAlerts'     => $d['widgets']['agent_performance_alerts'] ?? null,
            'dashboardMeta'       => $meta,
        ]);
    }
}
