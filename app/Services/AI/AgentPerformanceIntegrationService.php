<?php

namespace App\Services\AI;

use App\Models\AiSalesScore;
use App\Models\User;
use App\Services\AgentStatisticsService;
use Illuminate\Support\Facades\Schema;

/**
 * Integrates agent statistics, leaderboard, and payroll data
 * with AI Trainer, coaching, and Sales Intelligence systems.
 *
 * This service bridges the gap between performance metrics and actionable
 * coaching/training recommendations.
 */
class AgentPerformanceIntegrationService
{
    // ── Score thresholds ────────────────────────────────
    private const WEAK_CLOSE_RATE = 20.0;
    private const STRONG_CLOSE_RATE = 50.0;
    private const HIGH_PERFORMER_REVENUE = 50000;
    private const PAYROLL_ANOMALY_THRESHOLD = 2.0; // 2x standard deviation

    /**
     * Evaluate an agent's performance and trigger appropriate AI actions.
     * Called after leaderboard updates, deal closings, or payroll runs.
     */
    public static function evaluateAgent(User $user): array
    {
        $actions = [];

        try {
            $leaderboard = AgentStatisticsService::leaderboard(
                in_array($user->role, ['fronter', 'fronter_panama']) ? 'fronter' : (in_array($user->role, ['closer', 'closer_panama']) ? 'closer' : null),
                null, null, null, 50
            );

            $agentEntry = collect($leaderboard)->firstWhere('user_id', $user->id);
            if (!$agentEntry) return $actions;

            $rank = collect($leaderboard)->search(fn($a) => $a['user_id'] === $user->id);
            $totalAgents = count($leaderboard);

            // 1. Score-based coaching triggers
            $closeRate = $agentEntry['close_rate'] ?? 0;
            $revenue = $agentEntry['revenue'] ?? 0;
            $dealsClosed = $agentEntry['deals_closed'] ?? 0;

            if ($closeRate < self::WEAK_CLOSE_RATE && $dealsClosed > 0) {
                $actions[] = self::triggerCoachingSuggestion($user, $agentEntry);
            }

            // 2. High performer badge
            if ($rank !== false && $rank < 3) {
                $actions[] = self::triggerHighPerformerBadge($user, $rank + 1, $agentEntry);
            } elseif ($revenue >= self::HIGH_PERFORMER_REVENUE) {
                $actions[] = self::triggerHighPerformerBadge($user, null, $agentEntry);
            }

            // 3. Training recommendations based on weaknesses
            $weaknesses = self::detectWeaknesses($user, $agentEntry);
            foreach ($weaknesses as $weakness) {
                $actions[] = self::mapWeaknessToTraining($user, $weakness);
            }

            // 4. Store performance score
            self::upsertPerformanceScore($user, $agentEntry, $rank, $totalAgents);

        } catch (\Throwable $e) {
            report($e);
        }

        return $actions;
    }

    /**
     * Check for payroll anomalies and trigger admin alerts.
     */
    public static function checkPayrollAnomalies(User $user, float $currentPay): array
    {
        $alerts = [];

        try {
            // Get historical pay data
            $avgPay = \Illuminate\Support\Facades\DB::table('payroll_entries')
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subMonths(3))
                ->avg('net_pay');

            if (!$avgPay || $avgPay == 0) return $alerts;

            $ratio = $currentPay / $avgPay;

            // Flag if current pay is significantly different from average
            if ($ratio > self::PAYROLL_ANOMALY_THRESHOLD || $ratio < (1 / self::PAYROLL_ANOMALY_THRESHOLD)) {
                $direction = $ratio > 1 ? 'spike' : 'drop';
                $pctChange = round(abs($ratio - 1) * 100);

                $alerts[] = [
                    'type' => 'payroll_anomaly',
                    'severity' => $pctChange > 200 ? 'high' : 'medium',
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'message' => "Payroll {$direction}: {$user->name}'s pay is {$pctChange}% " .
                                ($direction === 'spike' ? 'higher' : 'lower') .
                                " than 3-month average (\${$avgPay} avg vs \${$currentPay} current)",
                    'current_pay' => $currentPay,
                    'avg_pay' => $avgPay,
                    'direction' => $direction,
                    'pct_change' => $pctChange,
                ];

                // Save as AI trainer event
                AiTrainerService::logEvent($user, 'payroll', null, null, 'payroll_anomaly', [
                    'current_pay' => $currentPay,
                    'avg_pay' => $avgPay,
                    'direction' => $direction,
                ], null, $pctChange > 200 ? 'high' : 'medium');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $alerts;
    }

    /**
     * Generate leaderboard change alerts for the dashboard.
     */
    public static function getLeaderboardAlerts($from = null, $to = null): array
    {
        $alerts = [];

        try {
            $leaderboard = AgentStatisticsService::leaderboard(null, null, $from, $to, 20);

            foreach ($leaderboard as $idx => $agent) {
                $badge = $agent['badge'] ?? null;

                if ($badge === 'High Performer' || $badge === 'Top Revenue') {
                    $alerts[] = [
                        'type' => 'leaderboard_achievement',
                        'severity' => 'positive',
                        'icon' => 'trophy',
                        'user_id' => $agent['user_id'],
                        'message' => "{$agent['name']} ({$agent['label']}) — {$badge}: \$" . number_format($agent['revenue'], 0) . " revenue, {$agent['deals_closed']} deals",
                    ];
                }

                if ($badge === 'Needs Improvement') {
                    $alerts[] = [
                        'type' => 'leaderboard_warning',
                        'severity' => 'warning',
                        'icon' => 'alert',
                        'user_id' => $agent['user_id'],
                        'message' => "{$agent['name']} ({$agent['label']}) needs coaching — {$agent['close_rate']}% close rate",
                    ];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $alerts;
    }

    /**
     * Get combined performance alerts for the dashboard.
     */
    public static function getDashboardAlerts($from = null, $to = null): array
    {
        $alerts = [];

        try {
            $insights = AgentStatisticsService::performanceInsights($from, $to);

            // Weakest fronter group alert
            if ($insights['weakest_fronter_group'] ?? null) {
                $wf = $insights['weakest_fronter_group'];
                $alerts[] = [
                    'type' => 'team_performance',
                    'severity' => 'warning',
                    'icon' => 'trending-down',
                    'message' => "Weakest fronter group: {$wf['label']} — {$wf['insight']}",
                ];
            }

            // Top performer alert
            if ($insights['top_performer'] ?? null) {
                $tp = $insights['top_performer'];
                $alerts[] = [
                    'type' => 'team_performance',
                    'severity' => 'positive',
                    'icon' => 'star',
                    'message' => "Top performer: {$tp['name']} ({$tp['label']}) — {$tp['deals_closed']} deals, \$" . number_format($tp['revenue'], 0),
                ];
            }

            // Leaderboard alerts
            $alerts = array_merge($alerts, self::getLeaderboardAlerts($from, $to));
        } catch (\Throwable $e) {
            report($e);
        }

        return $alerts;
    }

    // ── Private Methods ─────────────────────────────────

    private static function triggerCoachingSuggestion(User $user, array $entry): array
    {
        $closeRate = $entry['close_rate'] ?? 0;
        $role = $entry['role'] ?? 'agent';

        $message = $role === 'fronter'
            ? "Your transfer-to-deal conversion is at {$closeRate}%. Focus on qualifying leads more thoroughly before transferring."
            : "Your close rate is at {$closeRate}%. Review objection handling techniques and follow up more consistently.";

        AiTrainerService::saveRecommendation(
            $user,
            $role === 'fronter' ? 'leads' : 'deals',
            null, null,
            'performance_coaching',
            'Low Close Rate — Coaching Recommended',
            $message,
            'View Training',
            '/sales-training'
        );

        return ['action' => 'coaching_triggered', 'user' => $user->name, 'close_rate' => $closeRate];
    }

    private static function triggerHighPerformerBadge(User $user, ?int $rank, array $entry): array
    {
        $label = $rank ? "Rank #{$rank}" : 'Top Revenue';
        $revenue = $entry['revenue'] ?? 0;

        AiTrainerService::logEvent($user, 'performance', null, null, 'high_performer_badge', [
            'rank' => $rank,
            'revenue' => $revenue,
            'deals_closed' => $entry['deals_closed'] ?? 0,
            'label' => $entry['label'] ?? '',
        ], null);

        return ['action' => 'badge_awarded', 'user' => $user->name, 'badge' => $label, 'revenue' => $revenue];
    }

    private static function detectWeaknesses(User $user, array $entry): array
    {
        $weaknesses = [];
        $role = $entry['role'] ?? '';

        if ($role === 'fronter') {
            $transfers = $entry['transfers'] ?? 0;
            $closeRate = $entry['close_rate'] ?? 0;

            if ($transfers < 3) $weaknesses[] = ['type' => 'low_activity', 'detail' => 'Very few transfers — needs more lead engagement'];
            if ($closeRate < 15 && $transfers > 5) $weaknesses[] = ['type' => 'poor_qualification', 'detail' => 'Low conversion suggests poor lead qualification'];
        } else {
            $received = $entry['deals_received'] ?? 0;
            $closed = $entry['deals_closed'] ?? 0;
            $closeRate = $entry['close_rate'] ?? 0;

            if ($closeRate < 20 && $received > 3) $weaknesses[] = ['type' => 'low_close_rate', 'detail' => 'Close rate below threshold — review deal handling'];
            if ($received > 0 && $closed === 0) $weaknesses[] = ['type' => 'zero_closes', 'detail' => 'Received deals but closed none — needs immediate coaching'];
        }

        return $weaknesses;
    }

    private static function mapWeaknessToTraining(User $user, array $weakness): array
    {
        $trainingMap = [
            'low_activity' => [
                'title' => 'Increase Lead Engagement',
                'message' => 'Your transfer count is very low. Review the lead contact checklist and aim for at least 5 quality contacts per day.',
                'action' => '/sales-training',
            ],
            'poor_qualification' => [
                'title' => 'Improve Lead Qualification',
                'message' => 'Many of your transferred leads are not converting. Focus on verifying owner interest, timeshare details, and financial readiness before transferring.',
                'action' => '/sales-training',
            ],
            'low_close_rate' => [
                'title' => 'Improve Closing Techniques',
                'message' => 'Your close rate is below the team average. Review objection handling scripts and practice the closing sequence.',
                'action' => '/sales-training',
            ],
            'zero_closes' => [
                'title' => 'Urgent: Zero Deals Closed',
                'message' => 'You have received deals but have not closed any. Schedule a coaching session with your team lead immediately.',
                'action' => '/sales-training',
            ],
        ];

        $training = $trainingMap[$weakness['type']] ?? [
            'title' => 'Performance Review Needed',
            'message' => $weakness['detail'],
            'action' => '/sales-training',
        ];

        // Save as recommendation
        AiTrainerService::saveRecommendation(
            $user,
            in_array($user->role, ['fronter', 'fronter_panama']) ? 'leads' : 'deals',
            null, null,
            'training_recommendation',
            $training['title'],
            $training['message'],
            'Start Training',
            $training['action']
        );

        return [
            'action' => 'training_recommended',
            'user' => $user->name,
            'weakness' => $weakness['type'],
            'training' => $training['title'],
        ];
    }

    private static function upsertPerformanceScore(User $user, array $entry, int|false $rank, int $totalAgents): void
    {
        if (!Schema::hasTable('ai_sales_score')) return;

        $closeRate = $entry['close_rate'] ?? 0;
        $revenue = $entry['revenue'] ?? 0;
        $deals = $entry['deals_closed'] ?? 0;

        // Calculate composite score (0-100)
        $scoreComponents = [];
        $scoreComponents[] = min($closeRate, 100) * 0.4; // 40% weight on close rate
        $scoreComponents[] = min($revenue / 1000, 100) * 0.3; // 30% weight on revenue (scaled)
        $scoreComponents[] = min($deals * 10, 100) * 0.2; // 20% weight on deal count
        $scoreComponents[] = ($rank !== false && $totalAgents > 0)
            ? (1 - ($rank / $totalAgents)) * 100 * 0.1
            : 50 * 0.1; // 10% weight on rank

        $score = round(array_sum($scoreComponents));
        $label = match (true) {
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'average',
            $score >= 20 => 'below_average',
            default => 'needs_improvement',
        };

        $reasons = [];
        if ($closeRate >= 50) $reasons[] = 'High close rate';
        if ($closeRate < 20) $reasons[] = 'Low close rate needs attention';
        if ($revenue >= 50000) $reasons[] = 'Top revenue earner';
        if ($deals === 0) $reasons[] = 'No deals closed yet';
        if ($rank !== false && $rank < 3) $reasons[] = "Ranked #{$rank} on leaderboard";

        $risks = [];
        if ($closeRate < 15 && $deals > 0) $risks[] = 'Close rate trending dangerously low';
        if ($revenue === 0 && $deals > 0) $risks[] = 'Deals closed but no revenue recorded';

        $recommendations = [];
        if ($closeRate < 30) $recommendations[] = 'Review objection handling training';
        if ($deals < 2) $recommendations[] = 'Increase deal pipeline activity';

        try {
            AiSalesScore::upsertScore(
                'user', $user->id, 'agent_performance',
                $score, $label, 0.85,
                $reasons, $risks, $recommendations
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
