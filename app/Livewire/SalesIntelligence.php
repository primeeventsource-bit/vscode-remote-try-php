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

        $payload = DashboardDataService::getFullPayload($user, $filters);
        $d = $payload['data'];

        return view('livewire.sales-intelligence', [
            'user'                => $user,
            'isMaster'            => $user->hasRole('master_admin'),
            'isAdmin'             => $user->hasRole('master_admin', 'admin', 'admin_limited'),
            'isFronter'           => in_array($user->role, ['fronter', 'fronter_panama']),
            'isCloser'            => in_array($user->role, ['closer', 'closer_panama']),
            'users'               => $user->hasRole('master_admin', 'admin') ? User::orderBy('name')->get() : collect(),
            'summaryCards'        => $d['summary_cards'],
            'priorityAlerts'      => $d['priority_alerts'],
            'dealProbability'     => $d['charts']['deal_probability'],
            'pipelineRisk'        => $d['charts']['pipeline_risk'],
            'atRiskDeals'         => $d['tables']['at_risk_deals'],
            'hottestLeads'        => $d['tables']['hottest_leads'],
            'followupQueue'       => $d['tables']['followup_queue'],
            'coachingWatchlist'   => $d['widgets']['rep_coaching_watchlist'],
            'topMistakes'         => $d['widgets']['top_mistakes'],
            'recentScoreChanges'  => $d['widgets']['recent_score_changes'],
            'aiRecommendations'   => $d['widgets']['ai_recommendations'],
            'upcomingRevenue'     => $d['widgets']['upcoming_revenue'],
            'dashboardMeta'       => $payload['meta'],
        ]);
    }
}
