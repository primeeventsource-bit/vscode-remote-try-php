<?php

namespace App\Livewire;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\StatisticsRepository;
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
        $isCloser = $user->role === 'closer';
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

        if ($isMaster || $isAdmin) {
            // Admin/Master see company-wide deal stats
            $deals      = Deal::all();
            $totalLeads = Lead::count();
            $assignedLeads = Lead::whereNotNull('assigned_to')->count();
        } elseif ($isCloser) {
            // Closer only sees their own deals
            $deals      = Deal::where('closer', $user->id)->get();
            $totalLeads = 0;
            $assignedLeads = 0;
        } elseif ($isFronter) {
            // Fronter only sees leads they work
            $deals      = collect();
            $totalLeads = Lead::where('assigned_to', $user->id)->orWhere('original_fronter', $user->id)->count();
            $assignedLeads = Lead::where('assigned_to', $user->id)->count();
        } else {
            $deals = collect();
            $totalLeads = 0;
            $assignedLeads = 0;
        }

        $charged    = $deals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $chargebacks = $deals->where('charged_back', 'yes');
        $pending    = $deals->where('charged', '!=', 'yes')->where('status', '!=', 'cancelled');
        $cancelled  = $deals->where('status', 'cancelled');

        $totalRev = $charged->sum('fee');
        $cbRev    = $chargebacks->sum('fee');
        $pendRev  = $pending->sum('fee');

        $weekDeals   = $deals->filter(fn($d) => $d->timestamp && Carbon::parse($d->timestamp)->gte($weekStart));
        $weekCharged = $weekDeals->where('charged', 'yes')->where('charged_back', '!=', 'yes');
        $weekRev     = $weekCharged->sum('fee');

        // Monthly performance – last 6 months
        $monthlyData = collect();
        $monthlyChargebackData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();

            $monthCharged = $deals->filter(fn($d) =>
                $d->timestamp && Carbon::parse($d->timestamp)->gte($start)
                && Carbon::parse($d->timestamp)->lte($end)
                && $d->charged === 'yes' && $d->charged_back !== 'yes'
            );
            $monthChargebacks = $deals->filter(fn($d) =>
                $d->timestamp && Carbon::parse($d->timestamp)->gte($start)
                && Carbon::parse($d->timestamp)->lte($end)
                && $d->charged_back === 'yes'
            );

            $monthlyData->push(['label' => $month->format('M'), 'rev' => (float) $monthCharged->sum('fee'), 'count' => $monthCharged->count()]);
            $monthlyChargebackData->push(['label' => $month->format('M'), 'rev' => (float) $monthChargebacks->sum('fee'), 'count' => $monthChargebacks->count()]);
        }

        // Top closers only for master admin
        $closers = collect();
        if ($isMaster) {
            $allDeals = Deal::all();
            $closers = User::where('role', 'closer')->get()->map(function ($u) use ($allDeals) {
                $d = $allDeals->where('closer', $u->id)->where('charged', 'yes');
                return ['user' => $u, 'count' => $d->count(), 'rev' => $d->sum('fee')];
            })->sortByDesc('rev');
        }

        $recentDeals = $deals->sortByDesc('id')->take(5);

        // Task widget
        $taskWidget = ['overdue' => 0, 'due_today' => 0, 'open' => 0, 'urgent' => 0];
        try {
            $tq = DB::table('tasks')->where('status', 'open');
            if (!$isMaster) $tq->where('assigned_to', $user->id);

            $openTasks = $tq->get();
            $taskWidget['open'] = $openTasks->count();
            $taskWidget['urgent'] = $openTasks->where('priority', 'urgent')->count();
            $taskWidget['due_today'] = $openTasks->filter(fn($t) => $t->due_date && Carbon::parse($t->due_date)->isToday())->count();
            $taskWidget['overdue'] = $openTasks->filter(fn($t) => $t->due_date && Carbon::parse($t->due_date)->isPast())->count();
        } catch (\Throwable $e) {}

        return view('livewire.dashboard', compact(
            'totalLeads', 'assignedLeads', 'deals', 'charged', 'chargebacks',
            'pending', 'cancelled', 'totalRev', 'cbRev', 'pendRev',
            'weekDeals', 'weekCharged', 'weekRev', 'closers', 'recentDeals',
            'isFronter', 'isCloser', 'isAdmin', 'isMaster', 'userRole',
            'monthlyData', 'monthlyChargebackData', 'pipelineStats', 'taskWidget'
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
