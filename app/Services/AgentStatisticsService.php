<?php

namespace App\Services;

use App\Models\AgentStatDaily;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full statistics system for Fronters, Closers, Panama Fronters, Panama Closers.
 * All methods return plain arrays. All percentages are safe against division by zero.
 */
class AgentStatisticsService
{
    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    private static function safePct(int|float $num, int|float $den, int $precision = 1): float
    {
        return $den == 0 ? 0.0 : round($num / $den * 100, $precision);
    }

    private static function safeDiv(int|float $num, int|float $den, int $precision = 2): float
    {
        return $den == 0 ? 0.0 : round($num / $den, $precision);
    }

    private static function pipelineReady(): bool
    {
        try { return Schema::hasTable('pipeline_events'); } catch (\Throwable) { return false; }
    }

    // ══════════════════════════════════════════════════════════════
    // 1. SUMMARY — stat cards for fronters and closers
    // ══════════════════════════════════════════════════════════════

    public static function summary(?string $role = null, ?string $location = null, $from = null, $to = null): array
    {
        $fronterRoles = self::resolveRoles('fronter', $location);
        $closerRoles = self::resolveRoles('closer', $location);

        // Fronter totals
        $fronterUsers = User::whereIn('role', $fronterRoles)->pluck('id')->toArray();
        $closerUsers = User::whereIn('role', $closerRoles)->pluck('id')->toArray();

        $fronterData = self::computeFronterTotals($fronterUsers, $from, $to);
        $closerData = self::computeCloserTotals($closerUsers, $from, $to);

        return [
            'fronter' => [
                'total_leads' => $fronterData['total_leads'],
                'qualified_leads' => $fronterData['qualified'],
                'transfer_rate' => self::safePct($fronterData['transferred'], max($fronterData['total_leads'], 1)),
                'avg_contact_time' => $fronterData['avg_contact_time'],
                'transferred' => $fronterData['transferred'],
            ],
            'closer' => [
                'deals_closed' => $closerData['deals_closed'],
                'revenue' => $closerData['revenue'],
                'close_rate' => self::safePct($closerData['deals_closed'], max($closerData['deals_received'], 1)),
                'avg_deal_value' => self::safeDiv($closerData['revenue'], max($closerData['deals_closed'], 1)),
                'deals_received' => $closerData['deals_received'],
                'deals_lost' => $closerData['deals_lost'],
            ],
        ];
    }

    private static function computeFronterTotals(array $userIds, $from, $to): array
    {
        if (empty($userIds)) {
            return ['total_leads' => 0, 'qualified' => 0, 'transferred' => 0, 'avg_contact_time' => 0];
        }

        $leadQuery = Lead::whereIn('assigned_to', $userIds);
        if ($from) $leadQuery->where('created_at', '>=', $from);
        if ($to) $leadQuery->where('created_at', '<=', $to);
        $totalLeads = $leadQuery->count();

        $qualifiedQuery = Lead::whereIn('assigned_to', $userIds)
            ->whereIn('disposition', ['Qualified', 'Transferred to Closer', 'Converted to Deal']);
        if ($from) $qualifiedQuery->where('updated_at', '>=', $from);
        if ($to) $qualifiedQuery->where('updated_at', '<=', $to);
        $qualified = $qualifiedQuery->count();

        $transferred = 0;
        if (self::pipelineReady()) {
            $tq = DB::table('pipeline_events')
                ->where('event_type', 'TRANSFERRED_TO_CLOSER')
                ->whereIn('source_user_id', $userIds);
            if ($from) $tq->where('event_at', '>=', $from);
            if ($to) $tq->where('event_at', '<=', $to);
            $transferred = $tq->count();
        } else {
            $tq = Lead::whereIn('assigned_to', $userIds)->where('disposition', 'Transferred to Closer');
            if ($from) $tq->where('updated_at', '>=', $from);
            if ($to) $tq->where('updated_at', '<=', $to);
            $transferred = $tq->count();
        }

        // Avg first contact time (seconds) from transferred_at - created_at
        $avgContact = Lead::whereIn('assigned_to', $userIds)
            ->whereNotNull('transferred_at')
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to))
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, transferred_at)) as avg_sec')
            ->value('avg_sec');

        return [
            'total_leads' => $totalLeads,
            'qualified' => $qualified,
            'transferred' => $transferred,
            'avg_contact_time' => (int) ($avgContact ?? 0),
        ];
    }

    private static function computeCloserTotals(array $userIds, $from, $to): array
    {
        if (empty($userIds)) {
            return ['deals_received' => 0, 'deals_closed' => 0, 'deals_lost' => 0, 'revenue' => 0];
        }

        $dealsReceived = 0;
        $dealsClosed = 0;

        if (self::pipelineReady()) {
            $rq = DB::table('pipeline_events')
                ->where('event_type', 'TRANSFERRED_TO_CLOSER')
                ->whereIn('target_user_id', $userIds);
            if ($from) $rq->where('event_at', '>=', $from);
            if ($to) $rq->where('event_at', '<=', $to);
            $dealsReceived = $rq->count();

            $cq = DB::table('pipeline_events')
                ->where('event_type', 'CLOSER_CLOSED_DEAL')
                ->whereIn('performed_by_user_id', $userIds);
            if ($from) $cq->where('event_at', '>=', $from);
            if ($to) $cq->where('event_at', '<=', $to);
            $dealsClosed = $cq->count();
        } else {
            $dealsReceived = Lead::where('disposition', 'Transferred to Closer')
                ->whereIn('transferred_to_user_id', $userIds)
                ->when($from, fn($q) => $q->where('updated_at', '>=', $from))
                ->when($to, fn($q) => $q->where('updated_at', '<=', $to))
                ->count();

            $dq = Deal::whereIn('closer', $userIds);
            if ($from) $dq->where('created_at', '>=', $from);
            if ($to) $dq->where('created_at', '<=', $to);
            $dealsClosed = $dq->count();
        }

        // Revenue from charged deals
        $revQuery = Deal::whereIn('closer', $userIds)->where('charged', 'yes')
            ->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'));
        if ($from) $revQuery->where('charged_date', '>=', $from);
        if ($to) $revQuery->where('charged_date', '<=', $to);
        $revenue = (float) $revQuery->sum('fee');

        $dealsLost = max(0, $dealsReceived - $dealsClosed);

        return [
            'deals_received' => $dealsReceived,
            'deals_closed' => $dealsClosed,
            'deals_lost' => $dealsLost,
            'revenue' => $revenue,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // 2. ROLE BREAKDOWN — grouped by role + location
    // ══════════════════════════════════════════════════════════════

    public static function roleBreakdown($from = null, $to = null): array
    {
        $groups = [
            ['role' => 'fronter', 'location' => 'US', 'label' => 'Fronter (US)'],
            ['role' => 'fronter', 'location' => 'Panama', 'label' => 'Fronter (Panama)'],
            ['role' => 'closer', 'location' => 'US', 'label' => 'Closer (US)'],
            ['role' => 'closer', 'location' => 'Panama', 'label' => 'Closer (Panama)'],
        ];

        $result = [];
        foreach ($groups as $group) {
            $roles = self::resolveRoles($group['role'], $group['location']);
            $userIds = User::whereIn('role', $roles)->pluck('id')->toArray();

            $fronterData = $group['role'] === 'fronter'
                ? self::computeFronterTotals($userIds, $from, $to)
                : ['total_leads' => 0, 'qualified' => 0, 'transferred' => 0, 'avg_contact_time' => 0];

            $closerData = $group['role'] === 'closer'
                ? self::computeCloserTotals($userIds, $from, $to)
                : ['deals_received' => 0, 'deals_closed' => 0, 'deals_lost' => 0, 'revenue' => 0];

            $result[] = [
                'role' => $group['role'],
                'location' => $group['location'],
                'label' => $group['label'],
                'agent_count' => count($userIds),
                'leads' => $fronterData['total_leads'],
                'qualified' => $fronterData['qualified'],
                'transfers' => $fronterData['transferred'],
                'deals_closed' => $closerData['deals_closed'],
                'deals_lost' => $closerData['deals_lost'],
                'revenue' => $closerData['revenue'],
                'close_rate' => self::safePct(
                    $closerData['deals_closed'],
                    max($closerData['deals_received'], 1)
                ),
            ];
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════
    // 3. LEADERBOARD — per user stats ranked by revenue/deals
    // ══════════════════════════════════════════════════════════════

    public static function leaderboard(?string $role = null, ?string $location = null, $from = null, $to = null, int $limit = 20): array
    {
        $roles = [];
        if ($role) {
            $roles = self::resolveRoles($role, $location);
        } else {
            $roles = ['fronter', 'fronter_panama', 'closer', 'closer_panama'];
            if ($location === 'Panama') $roles = ['fronter_panama', 'closer_panama'];
            if ($location === 'US') $roles = ['fronter', 'closer'];
        }

        $users = User::whereIn('role', $roles)->orderBy('name')->get();
        $result = [];

        foreach ($users as $user) {
            $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);

            if ($isFronter) {
                $transferred = 0;
                $dealsClosed = 0;

                if (self::pipelineReady()) {
                    $tq = DB::table('pipeline_events')
                        ->where('event_type', 'TRANSFERRED_TO_CLOSER')
                        ->where('source_user_id', $user->id);
                    if ($from) $tq->where('event_at', '>=', $from);
                    if ($to) $tq->where('event_at', '<=', $to);
                    $sentLeadIds = $tq->pluck('lead_id')->unique()->toArray();
                    $transferred = count($sentLeadIds);

                    if ($transferred > 0) {
                        $dealsClosed = DB::table('pipeline_events')
                            ->where('event_type', 'CLOSER_CLOSED_DEAL')
                            ->whereIn('lead_id', $sentLeadIds)
                            ->distinct('lead_id')
                            ->count('lead_id');
                    }
                } else {
                    $tq = Lead::where(fn($q) => $q->where('assigned_to', $user->id)->orWhere('original_fronter', $user->id))
                        ->where('disposition', 'Transferred to Closer');
                    if ($from) $tq->where('updated_at', '>=', $from);
                    if ($to) $tq->where('updated_at', '<=', $to);
                    $transferred = $tq->count();

                    $dq = Deal::where('fronter', $user->id);
                    if ($from) $dq->where('created_at', '>=', $from);
                    if ($to) $dq->where('created_at', '<=', $to);
                    $dealsClosed = min($dq->count(), $transferred);
                }

                $revQuery = Deal::where('fronter', $user->id)->where('charged', 'yes')
                    ->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'));
                if ($from) $revQuery->where('charged_date', '>=', $from);
                if ($to) $revQuery->where('charged_date', '<=', $to);
                $revenue = (float) $revQuery->sum('fee');

                $result[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar ?? strtoupper(substr($user->name, 0, 2)),
                    'color' => $user->color ?? '#6b7280',
                    'role' => str_replace('_panama', '', $user->role ?? ''),
                    'location' => (str_contains($user->role ?? '', 'panama') ? 'Panama' : 'US'),
                    'label' => (ucfirst(str_replace('_panama', '', $user->role ?? '')) . ' (' . (str_contains($user->role ?? '', 'panama') ? 'Panama' : 'US') . ')'),
                    'transfers' => $transferred,
                    'deals_closed' => $dealsClosed,
                    'revenue' => $revenue,
                    'close_rate' => self::safePct($dealsClosed, max($transferred, 1)),
                    'badge' => self::computeBadge('fronter', $dealsClosed, $revenue, self::safePct($dealsClosed, max($transferred, 1))),
                ];
            } else {
                // Closer
                $received = 0;
                $closed = 0;

                if (self::pipelineReady()) {
                    $rq = DB::table('pipeline_events')
                        ->where('event_type', 'TRANSFERRED_TO_CLOSER')
                        ->where('target_user_id', $user->id);
                    if ($from) $rq->where('event_at', '>=', $from);
                    if ($to) $rq->where('event_at', '<=', $to);
                    $received = $rq->distinct('lead_id')->count('lead_id');

                    $cq = DB::table('pipeline_events')
                        ->where('event_type', 'CLOSER_CLOSED_DEAL')
                        ->where('performed_by_user_id', $user->id);
                    if ($from) $cq->where('event_at', '>=', $from);
                    if ($to) $cq->where('event_at', '<=', $to);
                    $closed = $cq->distinct('lead_id')->count('lead_id');
                } else {
                    $received = Lead::where('disposition', 'Transferred to Closer')
                        ->where('transferred_to', (string) $user->id)
                        ->when($from, fn($q) => $q->where('updated_at', '>=', $from))
                        ->when($to, fn($q) => $q->where('updated_at', '<=', $to))
                        ->count();

                    $dq = Deal::where('closer', $user->id);
                    if ($from) $dq->where('created_at', '>=', $from);
                    if ($to) $dq->where('created_at', '<=', $to);
                    $closed = $dq->count();
                }

                $revQuery = Deal::where('closer', $user->id)->where('charged', 'yes')
                    ->where(fn($q) => $q->where('charged_back', '!=', 'yes')->orWhereNull('charged_back'));
                if ($from) $revQuery->where('charged_date', '>=', $from);
                if ($to) $revQuery->where('charged_date', '<=', $to);
                $revenue = (float) $revQuery->sum('fee');

                $closeRate = self::safePct($closed, max($received, 1));
                $avgDealValue = self::safeDiv($revenue, max($closed, 1));

                $result[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar ?? strtoupper(substr($user->name, 0, 2)),
                    'color' => $user->color ?? '#6b7280',
                    'role' => str_replace('_panama', '', $user->role ?? ''),
                    'location' => (str_contains($user->role ?? '', 'panama') ? 'Panama' : 'US'),
                    'label' => (ucfirst(str_replace('_panama', '', $user->role ?? '')) . ' (' . (str_contains($user->role ?? '', 'panama') ? 'Panama' : 'US') . ')'),
                    'deals_received' => $received,
                    'deals_closed' => $closed,
                    'revenue' => $revenue,
                    'close_rate' => $closeRate,
                    'avg_deal_value' => $avgDealValue,
                    'badge' => self::computeBadge('closer', $closed, $revenue, $closeRate),
                ];
            }
        }

        // Sort by revenue descending
        usort($result, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        return array_slice($result, 0, $limit);
    }

    // ══════════════════════════════════════════════════════════════
    // 4. AI PERFORMANCE INSIGHTS
    // ══════════════════════════════════════════════════════════════

    public static function performanceInsights($from = null, $to = null): array
    {
        $breakdown = self::roleBreakdown($from, $to);

        $fronterGroups = array_filter($breakdown, fn($r) => $r['role'] === 'fronter');
        $closerGroups = array_filter($breakdown, fn($r) => $r['role'] === 'closer');

        // Weakest fronter group (lowest transfer count)
        $weakestFronter = null;
        $minTransfers = PHP_INT_MAX;
        foreach ($fronterGroups as $g) {
            if ($g['agent_count'] > 0 && $g['transfers'] < $minTransfers) {
                $minTransfers = $g['transfers'];
                $weakestFronter = $g;
            }
        }

        // Strongest closer group (highest close rate)
        $strongestCloser = null;
        $maxCloseRate = -1;
        foreach ($closerGroups as $g) {
            if ($g['agent_count'] > 0 && $g['close_rate'] > $maxCloseRate) {
                $maxCloseRate = $g['close_rate'];
                $strongestCloser = $g;
            }
        }

        // Highest converting team (highest close rate among all groups with deals)
        $highestConverting = null;
        $maxConversion = -1;
        foreach ($breakdown as $g) {
            $rate = $g['close_rate'] ?? 0;
            if ($g['agent_count'] > 0 && $rate > $maxConversion && ($g['deals_closed'] > 0 || $g['transfers'] > 0)) {
                $maxConversion = $rate;
                $highestConverting = $g;
            }
        }

        // Slowest follow-up team (check avg first contact time per group)
        $slowestFollowUp = null;
        $maxAvgTime = 0;
        foreach (['US', 'Panama'] as $loc) {
            $roles = self::resolveRoles('fronter', $loc);
            $ids = User::whereIn('role', $roles)->pluck('id')->toArray();
            if (empty($ids)) continue;

            $avgSec = Lead::whereIn('assigned_to', $ids)
                ->whereNotNull('transferred_at')
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to))
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, transferred_at)) as avg_sec')
                ->value('avg_sec') ?? 0;

            if ($avgSec > $maxAvgTime) {
                $maxAvgTime = $avgSec;
                $slowestFollowUp = ['label' => "Fronter ({$loc})", 'avg_seconds' => (int) $avgSec];
            }
        }

        // Top performer and bottom performer
        $leaderboard = self::leaderboard(null, null, $from, $to, 50);
        $topPerformer = !empty($leaderboard) ? $leaderboard[0] : null;
        $bottomPerformer = !empty($leaderboard) ? end($leaderboard) : null;

        return [
            'weakest_fronter_group' => $weakestFronter ? [
                'label' => $weakestFronter['label'],
                'transfers' => $weakestFronter['transfers'],
                'agents' => $weakestFronter['agent_count'],
                'insight' => "Lowest transfer volume with {$weakestFronter['transfers']} transfers from {$weakestFronter['agent_count']} agents",
            ] : null,

            'strongest_closer_group' => $strongestCloser ? [
                'label' => $strongestCloser['label'],
                'close_rate' => $strongestCloser['close_rate'],
                'deals_closed' => $strongestCloser['deals_closed'],
                'revenue' => $strongestCloser['revenue'],
                'insight' => "Highest close rate at {$strongestCloser['close_rate']}% with \${$strongestCloser['revenue']} revenue",
            ] : null,

            'slowest_follow_up_team' => $slowestFollowUp ? [
                'label' => $slowestFollowUp['label'],
                'avg_seconds' => $slowestFollowUp['avg_seconds'],
                'avg_formatted' => self::formatSeconds($slowestFollowUp['avg_seconds']),
                'insight' => "Avg first contact time of " . self::formatSeconds($slowestFollowUp['avg_seconds']),
            ] : null,

            'highest_converting_team' => $highestConverting ? [
                'label' => $highestConverting['label'],
                'close_rate' => $highestConverting['close_rate'] ?? 0,
                'insight' => "Best conversion rate in the organization",
            ] : null,

            'top_performer' => $topPerformer ? [
                'name' => $topPerformer['name'],
                'label' => $topPerformer['label'],
                'revenue' => $topPerformer['revenue'],
                'deals_closed' => $topPerformer['deals_closed'],
            ] : null,

            'bottom_performer' => $bottomPerformer ? [
                'name' => $bottomPerformer['name'],
                'label' => $bottomPerformer['label'],
                'revenue' => $bottomPerformer['revenue'],
            ] : null,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    private static function resolveRoles(string $baseRole, ?string $location = null): array
    {
        if ($location === 'Panama') return ["{$baseRole}_panama"];
        if ($location === 'US') return [$baseRole];
        return [$baseRole, "{$baseRole}_panama"];
    }

    private static function computeBadge(string $type, int $deals, float $revenue, float $closeRate): ?string
    {
        if ($type === 'closer') {
            if ($revenue >= 50000) return 'Top Revenue';
            if ($closeRate >= 60) return 'High Performer';
            if ($closeRate < 20 && $deals > 0) return 'Needs Improvement';
        } else {
            if ($closeRate >= 50) return 'High Performer';
            if ($deals >= 10) return 'Fast Responder';
            if ($closeRate < 15 && $deals > 0) return 'Needs Improvement';
        }
        return null;
    }

    private static function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return round($seconds / 60) . 'm';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return "{$h}h {$m}m";
    }
}
