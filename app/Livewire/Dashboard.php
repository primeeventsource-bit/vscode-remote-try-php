<?php

namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\StatisticsRepository;
use App\Services\AgentStatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public string $statsRange = 'live';

    public function render()
    {
        $user     = auth()->user();
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);
        $isCloser = in_array($user->role, ['closer', 'closer_panama']);
        $isAdmin  = in_array($user->role, ['admin', 'admin_limited']);
        $isMaster = $user->role === 'master_admin';

        // Compute date range for pipeline stats
        [$from, $to] = $this->computeDateRange();

        // ── Pipeline stats scoped to the authenticated user's role ──
        $pipelineStats = [];
        $userRole = 'viewer'; // for Blade

        try {
            if ($isFronter) {
                $pipelineStats = StatisticsRepository::getFronterDashboardStatsForUser($user, $from, $to);
                $userRole = 'fronter';
            } elseif ($isCloser) {
                $pipelineStats = StatisticsRepository::getCloserDashboardStatsForUser($user, $from, $to);
                $userRole = 'closer';
            } elseif ($isAdmin) {
                $pipelineStats = StatisticsRepository::getAdminDashboardStatsForUser($user, $from, $to);
                $userRole = 'admin';
            } elseif ($isMaster) {
                $pipelineStats = StatisticsRepository::getOverallSummary($from, $to);
                $userRole = 'master_admin';
            }
        } catch (\Throwable $e) {
            // Pipeline stats fail gracefully if tables/columns don't exist yet
            report($e);
        }

        // ── Existing dashboard data (scoped by role) ────────────────
        $now = now();
        $weekStart = $now->copy()->startOfWeek();

        // ── DB aggregation instead of loading all deals into memory ──
        $totalLeads = 0;
        $assignedLeads = 0;

        $dealScope = Deal::query();
        if ($isCloser) {
            $dealScope->where('closer', $user->id);
        } elseif (!$isMaster && !$isAdmin) {
            $dealScope->where('id', 0); // No deals for other roles
        }

        if ($isFronter) {
            $totalLeads = Lead::where('assigned_to', $user->id)->orWhere('original_fronter', $user->id)->count();
            $assignedLeads = Lead::where('assigned_to', $user->id)->count();
        } elseif ($isMaster || $isAdmin) {
            $totalLeads = Lead::count();
            $assignedLeads = Lead::whereNotNull('assigned_to')->count();
        }

        // Single aggregation query for deal stats
        $stats = (clone $dealScope)->selectRaw("
            SUM(CASE WHEN charged = 'yes' AND (charged_back != 'yes' OR charged_back IS NULL) THEN fee ELSE 0 END) as total_rev,
            SUM(CASE WHEN charged_back = 'yes' THEN fee ELSE 0 END) as cb_rev,
            SUM(CASE WHEN charged != 'yes' AND status != 'cancelled' THEN fee ELSE 0 END) as pend_rev,
            SUM(CASE WHEN charged = 'yes' AND (charged_back != 'yes' OR charged_back IS NULL) THEN 1 ELSE 0 END) as charged_count,
            SUM(CASE WHEN charged_back = 'yes' THEN 1 ELSE 0 END) as cb_count,
            SUM(CASE WHEN charged != 'yes' AND status != 'cancelled' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            COUNT(*) as total_deals
        ")->first();

        $totalRev = (float) ($stats->total_rev ?? 0);
        $cbRev = (float) ($stats->cb_rev ?? 0);
        $pendRev = (float) ($stats->pend_rev ?? 0);

        // Create small collections so Blade ->count() and ->sum() work
        $chargedCount = (int) ($stats->charged_count ?? 0);
        $cbCount = (int) ($stats->cb_count ?? 0);
        $pendingCount = (int) ($stats->pending_count ?? 0);
        $cancelledCount = (int) ($stats->cancelled_count ?? 0);
        $totalDealsCount = (int) ($stats->total_deals ?? 0);

        // Wrap as countable objects so Blade $var->count() calls work
        $charged = collect(array_fill(0, $chargedCount, null));
        $chargebacks = collect(array_fill(0, $cbCount, null));
        $pending = collect(array_fill(0, $pendingCount, null));
        $cancelled = collect(array_fill(0, $cancelledCount, null));
        $deals = collect(array_fill(0, $totalDealsCount, null));

        // Week stats via DB
        $weekStats = (clone $dealScope)->where('charged', 'yes')
            ->where(function($q) { $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'); })
            ->where('timestamp', '>=', $weekStart)
            ->selectRaw('SUM(fee) as rev, COUNT(*) as cnt')->first();
        $weekRev = (float) ($weekStats->rev ?? 0);
        $weekChargedCount = (int) ($weekStats->cnt ?? 0);
        $weekCharged = collect(array_fill(0, $weekChargedCount, null));
        $weekDeals = collect(array_fill(0, $weekChargedCount, null));

        // Monthly performance via DB – last 6 months
        $monthlyData = collect();
        $monthlyChargebackData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $mStats = (clone $dealScope)->whereBetween('timestamp', [$start, $end])
                ->selectRaw("
                    SUM(CASE WHEN charged = 'yes' AND (charged_back != 'yes' OR charged_back IS NULL) THEN fee ELSE 0 END) as rev,
                    SUM(CASE WHEN charged = 'yes' AND (charged_back != 'yes' OR charged_back IS NULL) THEN 1 ELSE 0 END) as cnt,
                    SUM(CASE WHEN charged_back = 'yes' THEN fee ELSE 0 END) as cb_rev,
                    SUM(CASE WHEN charged_back = 'yes' THEN 1 ELSE 0 END) as cb_cnt
                ")->first();

            $monthlyData->push(['label' => $month->format('M'), 'rev' => (float) ($mStats->rev ?? 0), 'count' => (int) ($mStats->cnt ?? 0)]);
            $monthlyChargebackData->push(['label' => $month->format('M'), 'rev' => (float) ($mStats->cb_rev ?? 0), 'count' => (int) ($mStats->cb_cnt ?? 0)]);
        }

        // Top closers via DB aggregation
        $closers = collect();
        if ($isMaster) {
            $closers = DB::table('deals')
                ->join('users', 'deals.closer', '=', 'users.id')
                ->where('deals.charged', 'yes')
                ->where(function($q) { $q->where('deals.charged_back', '!=', 'yes')->orWhereNull('deals.charged_back'); })
                ->selectRaw('users.id, users.name, users.color, users.avatar, COUNT(*) as count, SUM(deals.fee) as rev')
                ->groupBy('users.id', 'users.name', 'users.color', 'users.avatar')
                ->orderByDesc('rev')
                ->get()
                ->map(fn($r) => ['user' => (object) $r, 'count' => $r->count, 'rev' => (float) $r->rev]);
        }

        $recentDeals = (clone $dealScope)->orderByDesc('id')->limit(5)->get();

        // ── Agent Stats for Dashboard ────────────────────────────
        $agentSummary = [];
        $roleBreakdown = [];
        $topAgents = [];
        $aiInsights = [];
        $canSeeStats = $isMaster || $isAdmin;

        if ($canSeeStats) {
            try {
                $agentSummary = AgentStatisticsService::summary(null, null, $from, $to);
                $roleBreakdown = AgentStatisticsService::roleBreakdown($from, $to);
                $topAgents = AgentStatisticsService::leaderboard(null, null, $from, $to, 10);
                $aiInsights = AgentStatisticsService::performanceInsights($from, $to);
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif ($isFronter || $isCloser) {
            // Agents see their own position on the leaderboard
            try {
                $topAgents = AgentStatisticsService::leaderboard(null, null, $from, $to, 10);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Task widget — full task list for admin/master_admin
        $taskWidget = ['overdue' => 0, 'due_today' => 0, 'open' => 0, 'urgent' => 0];
        $dashboardTasks = collect();
        $showTaskScreen = ($isMaster || $isAdmin);
        try {
            $tq = DB::table('tasks')->where('status', 'open')->orderBy('due_date');
            // Admin and master admin see ALL tasks
            if (!$isMaster && !$isAdmin) {
                $tq->where('assigned_to', $user->id);
            }

            $openTasks = $tq->get();
            $taskWidget['open'] = $openTasks->count();
            $taskWidget['urgent'] = $openTasks->where('priority', 'urgent')->count();
            $taskWidget['due_today'] = $openTasks->filter(fn($t) => $t->due_date && Carbon::parse($t->due_date)->isToday())->count();
            $taskWidget['overdue'] = $openTasks->filter(fn($t) => $t->due_date && Carbon::parse($t->due_date)->isPast())->count();

            if ($showTaskScreen) {
                $dashboardTasks = $openTasks;
            }
        } catch (\Throwable $e) {}

        return view('livewire.dashboard', compact(
            'totalLeads', 'assignedLeads', 'deals', 'charged', 'chargebacks',
            'pending', 'cancelled', 'totalRev', 'cbRev', 'pendRev',
            'weekDeals', 'weekCharged', 'weekRev', 'closers', 'recentDeals',
            'isFronter', 'isCloser', 'isAdmin', 'isMaster', 'userRole',
            'monthlyData', 'monthlyChargebackData', 'pipelineStats',
            'taskWidget', 'dashboardTasks', 'showTaskScreen',
            'agentSummary', 'roleBreakdown', 'topAgents', 'aiInsights', 'canSeeStats'
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
