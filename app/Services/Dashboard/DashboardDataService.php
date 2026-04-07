<?php

namespace App\Services\Dashboard;

use App\Models\AiSalesScore;
use App\Models\AiTrainerMistake;
use App\Models\AiTrainerRecommendation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Produces typed, contract-stable payloads for every Sales Intelligence dashboard widget.
 * Each method returns an array matching the exact data contract for its widget.
 * All methods accept a User (for role-scoping) and DashboardFilterData.
 */
class DashboardDataService
{
    // ═══════════════════════════════════════════════════════
    // FULL PAGE PAYLOAD
    // ═══════════════════════════════════════════════════════

    public static function getFullPayload(User $user, DashboardFilterData $filters): array
    {
        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin', 'admin_limited');
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);
        $isCloser = in_array($user->role, ['closer', 'closer_panama']);

        $payload = [
            'header' => self::getHeader($user, $filters),
            'summary_cards' => self::getSummaryCards($user, $filters),
            'priority_alerts' => self::getPriorityAlerts($user, $filters),
            'charts' => [
                'deal_probability' => !$isFronter ? self::getDealProbabilityChart($user, $filters) : null,
                'pipeline_risk' => !$isFronter ? self::getPipelineRiskChart($user, $filters) : null,
            ],
            'tables' => [
                'at_risk_deals' => !$isFronter ? self::getAtRiskDeals($user, $filters) : null,
                'hottest_leads' => self::getHottestLeads($user, $filters),
                'followup_queue' => self::getFollowupQueue($user, $filters),
            ],
            'widgets' => [
                'rep_coaching_watchlist' => $isAdmin ? self::getRepCoachingWatchlist($user, $filters) : null,
                'top_mistakes' => $isAdmin ? self::getTopMistakes($user, $filters) : null,
                'recent_score_changes' => self::getRecentScoreChanges($user, $filters),
                'ai_recommendations' => self::getAiRecommendations($user, $filters),
                'upcoming_revenue' => ($isAdmin || $isCloser) ? self::getUpcomingRevenue($user, $filters) : null,
            ],
        ];

        return [
            'data' => $payload,
            'meta' => self::meta($user, $filters),
        ];
    }

    // ═══════════════════════════════════════════════════════
    // HEADER
    // ═══════════════════════════════════════════════════════

    public static function getHeader(User $user, DashboardFilterData $filters): array
    {
        $isAdmin = $user->hasRole('master_admin', 'admin', 'admin_limited');
        $isMaster = $user->hasRole('master_admin');

        return [
            'title' => 'Sales Intelligence Dashboard',
            'subtitle' => 'Live AI scoring, follow-up urgency, close probability, and coaching insights.',
            'filters' => [
                'date_range' => [
                    'selected' => $filters->dateRangeKey,
                    'options' => [
                        ['label' => 'Today', 'value' => 'today'],
                        ['label' => 'Last 7 Days', 'value' => '7d'],
                        ['label' => 'Last 30 Days', 'value' => '30d'],
                        ['label' => 'This Month', 'value' => 'month'],
                    ],
                ],
                'owners' => $isAdmin ? User::orderBy('name')->get()->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role,
                ])->toArray() : [],
            ],
            'actions' => [
                'can_refresh' => true,
                'can_export' => $isMaster,
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // KPI SUMMARY CARDS
    // ═══════════════════════════════════════════════════════

    public static function getSummaryCards(User $user, DashboardFilterData $filters): array
    {
        $isAdmin = $user->hasRole('master_admin', 'admin', 'admin_limited');
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);
        $isCloser = in_array($user->role, ['closer', 'closer_panama']);

        $leadQ = Lead::query();
        $dealQ = Deal::query();
        if ($isFronter) $leadQ->where('assigned_to', $user->id);
        if ($isCloser) $dealQ->where('closer', $user->id);
        if ($filters->ownerId) {
            $leadQ->where('assigned_to', $filters->ownerId);
            $dealQ->where(fn($q) => $q->where('closer', $filters->ownerId)->orWhere('fronter', $filters->ownerId));
        }

        $activeLeads = (clone $leadQ)->whereNotIn('disposition', ['Wrong Number', 'Disconnected'])->orWhereNull('disposition')->count();
        $hotLeads = self::scoreCount('lead', 'lead_score', 'hot');
        $likelyClose = self::scoreCountAbove('deal', 'close_probability', 70);
        $atRisk = self::scoreCountIn('deal', 'close_probability', ['at_risk', 'weak']);

        $forecast = 0;
        if (Schema::hasTable('ai_sales_scores')) {
            $scores = AiSalesScore::where('entity_type', 'deal')->where('score_type', 'close_probability')->get();
            foreach ($scores as $s) {
                $deal = Deal::find($s->entity_id);
                if ($deal) $forecast += ((float) ($deal->fee ?? 0)) * ($s->numeric_score / 100);
            }
        }

        $overdue = Lead::where(function ($q) {
            $q->where(fn($q2) => $q2->where('disposition', 'Callback')->where('callback_date', '<', now()))
              ->orWhere(fn($q2) => $q2->whereNull('disposition')->where('created_at', '<', now()->subDays(3)));
        })->when($isFronter, fn($q) => $q->where('assigned_to', $user->id))->count();

        $cards = [
            self::card('active_leads', 'Active Leads', 'Leads currently in your pipeline',
                $activeLeads, null, null, 'blue',
                'Shows all leads that are not closed or marked not interested.'),
            self::card('hot_leads', 'Hot Leads', 'Updated by AI scoring engine',
                $hotLeads, 'Hot', 'hot', 'red',
                'AI-detected leads with strong engagement and high conversion potential.'),
            self::card('likely_to_close', 'Likely to Close', 'Based on deal scoring',
                $likelyClose, '80%+', 'strong', 'emerald',
                'Deals with an AI-estimated close probability above 80%.'),
            self::card('at_risk_deals', 'At-Risk Deals', 'AI risk signals detected',
                $atRisk, $atRisk > 0 ? 'Needs Attention' : null, 'at_risk', 'amber',
                'Deals flagged due to inactivity, weak signals, or missing follow-up.'),
        ];

        if ($isAdmin || $isCloser) {
            $cards[] = self::card('weighted_forecast', 'Weighted Forecast', 'AI-adjusted projection',
                $forecast, null, null, 'purple',
                'Estimated revenue based on deal values weighted by close probability.', '$');
        }

        $cards[] = self::card('overdue_followups', 'Overdue Follow-Ups', 'Based on follow-up intelligence',
            $overdue, $overdue > 0 ? 'Urgent' : null, 'urgent', $overdue > 0 ? 'red' : 'gray',
            'Records that require immediate follow-up based on AI timing signals.');

        return $cards;
    }

    // ═══════════════════════════════════════════════════════
    // PRIORITY ALERTS
    // ═══════════════════════════════════════════════════════

    public static function getPriorityAlerts(User $user, DashboardFilterData $filters): array
    {
        $alerts = [];
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);

        // Hot leads not contacted today
        if (Schema::hasTable('ai_sales_scores')) {
            $hotUntouched = AiSalesScore::where('entity_type', 'lead')
                ->where('score_type', 'lead_score')
                ->where('label', 'hot')
                ->limit(5)->get();

            foreach ($hotUntouched as $s) {
                $lead = Lead::find($s->entity_id);
                if (!$lead || $lead->disposition) continue;
                if ($isFronter && $lead->assigned_to !== $user->id) continue;

                $assigned = $lead->assigned_to ? User::find($lead->assigned_to) : null;
                $alerts[] = [
                    'id' => 'alert_hot_lead_' . $lead->id,
                    'severity' => 'high',
                    'title' => 'Hot lead not contacted',
                    'message' => 'This lead has high intent but has not been contacted today.',
                    'entity_type' => 'lead',
                    'entity_id' => $lead->id,
                    'entity_name' => $lead->owner_name ?? 'Unknown',
                    'owner' => $assigned ? ['id' => $assigned->id, 'name' => $assigned->name] : null,
                    'action' => [
                        'label' => 'Contact Now',
                        'type' => 'open_lead',
                        'target_url' => '/leads',
                    ],
                    'created_at' => now()->toIso8601String(),
                ];
            }

            // At-risk deals
            $atRiskScores = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->whereIn('label', ['at_risk', 'weak'])
                ->orderBy('numeric_score')
                ->limit(3)->get();

            foreach ($atRiskScores as $s) {
                $deal = Deal::find($s->entity_id);
                if (!$deal) continue;

                $alerts[] = [
                    'id' => 'alert_risk_deal_' . $deal->id,
                    'severity' => $s->label === 'at_risk' ? 'high' : 'medium',
                    'title' => 'Deal at risk',
                    'message' => 'This deal shows signs of stalling due to no recent activity.',
                    'entity_type' => 'deal',
                    'entity_id' => $deal->id,
                    'entity_name' => $deal->owner_name ?? 'Unknown',
                    'owner' => null,
                    'action' => [
                        'label' => 'Review Deal',
                        'type' => 'open_deal',
                        'target_url' => '/deals',
                    ],
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        // Overdue callbacks
        $overdueCallbacks = Lead::where('disposition', 'Callback')
            ->where('callback_date', '<', now())
            ->when($isFronter, fn($q) => $q->where('assigned_to', $user->id))
            ->limit(3)->get();

        foreach ($overdueCallbacks as $lead) {
            $alerts[] = [
                'id' => 'alert_overdue_' . $lead->id,
                'severity' => 'high',
                'title' => 'Follow-up overdue',
                'message' => 'Callback was due ' . ($lead->callback_date?->diffForHumans() ?? 'recently') . '.',
                'entity_type' => 'lead',
                'entity_id' => $lead->id,
                'entity_name' => $lead->owner_name ?? 'Unknown',
                'owner' => null,
                'action' => [
                    'label' => 'Follow Up',
                    'type' => 'open_lead',
                    'target_url' => '/leads',
                ],
                'created_at' => now()->toIso8601String(),
            ];
        }

        return [
            'title' => 'AI Priority Alerts',
            'subtitle' => 'Immediate actions that need attention',
            'items' => array_slice($alerts, 0, 8),
            'meta' => ['empty' => empty($alerts), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // DEAL PROBABILITY CHART
    // ═══════════════════════════════════════════════════════

    public static function getDealProbabilityChart(User $user, DashboardFilterData $filters): array
    {
        $bins = [
            ['label' => '80-100%', 'min' => 80, 'max' => 100, 'count' => 0, 'weighted_revenue' => 0],
            ['label' => '60-79%',  'min' => 60, 'max' => 79,  'count' => 0, 'weighted_revenue' => 0],
            ['label' => '40-59%',  'min' => 40, 'max' => 59,  'count' => 0, 'weighted_revenue' => 0],
            ['label' => '20-39%',  'min' => 20, 'max' => 39,  'count' => 0, 'weighted_revenue' => 0],
            ['label' => '0-19%',   'min' => 0,  'max' => 19,  'count' => 0, 'weighted_revenue' => 0],
        ];

        if (Schema::hasTable('ai_sales_scores')) {
            $scores = AiSalesScore::where('entity_type', 'deal')->where('score_type', 'close_probability')->get();
            foreach ($scores as $s) {
                $deal = Deal::find($s->entity_id);
                $fee = (float) ($deal->fee ?? 0);
                foreach ($bins as &$bin) {
                    if ($s->numeric_score >= $bin['min'] && $s->numeric_score <= $bin['max']) {
                        $bin['count']++;
                        $bin['weighted_revenue'] += $fee * ($s->numeric_score / 100);
                        break;
                    }
                }
                unset($bin);
            }
        }

        $totalCount = array_sum(array_column($bins, 'count'));
        $totalRev = array_sum(array_column($bins, 'weighted_revenue'));

        return [
            'title' => 'Deal Close Probability',
            'subtitle' => 'Distribution of deals by likelihood to close',
            'chart_type' => 'horizontal_bar',
            'series' => array_map(fn($b) => [
                'label' => $b['label'],
                'count' => $b['count'],
                'weighted_revenue' => round($b['weighted_revenue']),
            ], $bins),
            'totals' => ['deal_count' => $totalCount, 'weighted_revenue' => round($totalRev)],
            'tooltip' => 'Shows how your deals are distributed across probability ranges.',
        ];
    }

    // ═══════════════════════════════════════════════════════
    // PIPELINE RISK CHART
    // ═══════════════════════════════════════════════════════

    public static function getPipelineRiskChart(User $user, DashboardFilterData $filters): array
    {
        $riskCounts = [];
        if (Schema::hasTable('ai_sales_scores')) {
            $atRisk = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->whereIn('label', ['at_risk', 'weak'])->get();

            foreach ($atRisk as $s) {
                foreach ($s->risks_json ?? [] as $r) {
                    $key = Str::limit($r, 35);
                    $riskCounts[$key] = ($riskCounts[$key] ?? 0) + 1;
                }
            }
            arsort($riskCounts);
        }

        $total = array_sum($riskCounts);
        $series = [];
        foreach (array_slice($riskCounts, 0, 6, true) as $label => $count) {
            $series[] = [
                'label' => $label,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ];
        }

        return [
            'title' => 'Pipeline Risk Breakdown',
            'subtitle' => 'Where deal risk is coming from',
            'chart_type' => 'donut',
            'series' => $series,
            'total_at_risk' => $total,
            'tooltip' => 'Highlights the most common reasons deals are at risk.',
        ];
    }

    // ═══════════════════════════════════════════════════════
    // AT-RISK DEALS TABLE
    // ═══════════════════════════════════════════════════════

    public static function getAtRiskDeals(User $user, DashboardFilterData $filters, int $limit = 10): array
    {
        $rows = [];
        if (Schema::hasTable('ai_sales_scores')) {
            $scores = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->whereIn('label', ['at_risk', 'weak'])
                ->orderBy('numeric_score')
                ->limit($limit)->get();

            foreach ($scores as $s) {
                $deal = Deal::find($s->entity_id);
                if (!$deal) continue;
                $closer = $deal->closer ? User::find($deal->closer) : null;

                $rows[] = [
                    'id' => $deal->id,
                    'deal_name' => $deal->owner_name ?? 'Unknown',
                    'deal_url' => '/deals',
                    'owner' => $closer ? ['id' => $closer->id, 'name' => $closer->name, 'avatar_url' => $closer->avatar_path ? '/storage/' . $closer->avatar_path : null] : null,
                    'value' => (float) ($deal->fee ?? 0),
                    'value_display' => '$' . number_format((float) ($deal->fee ?? 0)),
                    'close_probability' => $s->numeric_score,
                    'close_probability_label' => self::probLabel($s->numeric_score),
                    'risk_level' => $s->label === 'at_risk' ? 'high' : 'medium',
                    'risk_level_label' => $s->label === 'at_risk' ? 'High Risk' : 'Medium Risk',
                    'last_contact_at' => $deal->updated_at?->toIso8601String(),
                    'last_contact_display' => $deal->updated_at?->diffForHumans() ?? '--',
                    'next_best_action' => ($s->recommendations_json ?? [])[0] ?? 'Review deal',
                    'why_summary' => $s->risks_json ?? [],
                    'actions' => [
                        ['label' => 'Open', 'type' => 'link', 'target' => '/deals'],
                        ['label' => 'View Why', 'type' => 'drilldown', 'target' => "deal:{$deal->id}:close_probability"],
                    ],
                ];
            }
        }

        return [
            'title' => 'At-Risk Deals',
            'subtitle' => 'Deals requiring immediate attention',
            'tooltip' => 'Deals flagged by AI as likely to stall or be lost.',
            'rows' => $rows,
            'meta' => ['empty' => empty($rows), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // HOTTEST LEADS TABLE
    // ═══════════════════════════════════════════════════════

    public static function getHottestLeads(User $user, DashboardFilterData $filters, int $limit = 10): array
    {
        $rows = [];
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);

        if (Schema::hasTable('ai_sales_scores')) {
            $scores = AiSalesScore::where('entity_type', 'lead')
                ->where('score_type', 'lead_score')
                ->where('numeric_score', '>=', 45)
                ->orderByDesc('numeric_score')
                ->limit($limit)->get();

            foreach ($scores as $s) {
                $lead = Lead::find($s->entity_id);
                if (!$lead) continue;
                if ($isFronter && $lead->assigned_to !== $user->id) continue;

                $assigned = $lead->assigned_to ? User::find($lead->assigned_to) : null;
                $rows[] = [
                    'id' => $lead->id,
                    'lead_name' => $lead->owner_name ?? 'Unknown',
                    'lead_url' => '/leads',
                    'owner' => $assigned ? ['id' => $assigned->id, 'name' => $assigned->name, 'avatar_url' => $assigned->avatar_path ? '/storage/' . $assigned->avatar_path : null] : null,
                    'lead_score' => $s->numeric_score,
                    'lead_score_label' => self::leadLabel($s->label),
                    'ghost_risk' => max(0, 100 - $s->numeric_score),
                    'ghost_risk_label' => $s->numeric_score >= 60 ? 'Low' : ($s->numeric_score >= 35 ? 'Medium' : 'High'),
                    'next_best_action' => ($s->recommendations_json ?? [])[0] ?? 'Review lead',
                    'reasons' => $s->reasons_json ?? [],
                    'risks' => $s->risks_json ?? [],
                    'actions' => [
                        ['label' => 'Open', 'type' => 'link', 'target' => '/leads'],
                        ['label' => 'View Insight', 'type' => 'drilldown', 'target' => "lead:{$lead->id}:lead_score"],
                    ],
                ];
            }
        }

        return [
            'title' => 'Hottest Leads',
            'subtitle' => 'Leads most likely to convert',
            'tooltip' => 'Top leads ranked by AI score and engagement signals.',
            'rows' => $rows,
            'meta' => ['empty' => empty($rows), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // FOLLOW-UP QUEUE
    // ═══════════════════════════════════════════════════════

    public static function getFollowupQueue(User $user, DashboardFilterData $filters, int $limit = 20): array
    {
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);

        $leads = Lead::where(function ($q) {
            $q->where(fn($q2) => $q2->where('disposition', 'Callback')->where('callback_date', '<', now()->addHours(24)))
              ->orWhere(fn($q2) => $q2->whereNull('disposition')->where('created_at', '<', now()->subDays(2)))
              ->orWhere(fn($q2) => $q2->where('disposition', 'Left Voice Mail')->where('updated_at', '<', now()->subDays(1)));
        })
        ->when($isFronter, fn($q) => $q->where('assigned_to', $user->id))
        ->when($filters->ownerId, fn($q) => $q->where('assigned_to', $filters->ownerId))
        ->orderBy('updated_at')
        ->limit($limit)->get();

        $rows = $leads->map(function ($lead) {
            $assigned = $lead->assigned_to ? User::find($lead->assigned_to) : null;
            $isOverdue = $lead->disposition === 'Callback' && $lead->callback_date?->isPast();
            $isStale = !$lead->disposition && $lead->created_at?->diffInDays(now()) > 5;

            $priority = $isOverdue || $isStale ? 'high' : ($lead->disposition === 'Left Voice Mail' ? 'low' : 'medium');

            return [
                'id' => 'lead_' . $lead->id,
                'entity_type' => 'lead',
                'entity_id' => $lead->id,
                'name' => $lead->owner_name ?? 'Unknown',
                'owner' => $assigned ? ['id' => $assigned->id, 'name' => $assigned->name] : null,
                'priority' => $priority,
                'priority_label' => ucfirst($priority),
                'recommended_channel' => 'call',
                'recommended_channel_label' => 'Call',
                'recommended_time' => $isOverdue ? 'Now' : 'Today',
                'suggested_action' => $isOverdue ? 'Callback is overdue — call immediately' : 'Follow up to maintain engagement',
                'disposition' => $lead->disposition ?? 'Undisposed',
                'age_days' => $lead->created_at?->diffInDays(now()) ?? 0,
                'callback_date' => $lead->callback_date?->toIso8601String(),
            ];
        })->toArray();

        return [
            'title' => 'Follow-Up Queue',
            'subtitle' => 'Who needs contact right now',
            'tooltip' => 'Prioritized list of leads and deals requiring follow-up.',
            'rows' => $rows,
            'meta' => ['empty' => empty($rows), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // REP COACHING WATCHLIST
    // ═══════════════════════════════════════════════════════

    public static function getRepCoachingWatchlist(User $user, DashboardFilterData $filters): array
    {
        $rows = [];
        if (Schema::hasTable('ai_trainer_mistakes')) {
            $userMistakes = AiTrainerMistake::select('user_id')
                ->selectRaw('COUNT(*) as cnt')
                ->where('detected_at', '>=', $filters->from)
                ->whereNull('resolved_at')
                ->groupBy('user_id')
                ->orderByDesc('cnt')
                ->limit(10)->get();

            foreach ($userMistakes as $um) {
                $u = User::find($um->user_id);
                if (!$u) continue;

                $topMistake = AiTrainerMistake::where('user_id', $um->user_id)
                    ->whereNull('resolved_at')
                    ->orderByDesc('detected_at')->first();

                $rows[] = [
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'profile_url' => '/users',
                    'role' => ucfirst(str_replace('_', ' ', $u->role)),
                    'avatar' => $u->avatar ?? substr($u->name, 0, 2),
                    'color' => $u->color ?? '#6b7280',
                    'weakness' => $topMistake ? ucfirst(str_replace('_', ' ', $topMistake->mistake_type)) : '--',
                    'severity' => $topMistake?->severity ?? 'low',
                    'severity_label' => match ($topMistake?->severity ?? 'low') {
                        'high' => 'Needs Coaching', 'medium' => 'Improving', default => 'Low',
                    },
                    'suggested_topic' => $topMistake ? 'Address: ' . str_replace('_', ' ', $topMistake->mistake_type) : null,
                    'mistake_count' => $um->cnt,
                ];
            }
        }

        return [
            'title' => 'Rep Coaching Watchlist',
            'subtitle' => 'Users who need attention',
            'tooltip' => 'Highlights reps with performance issues or coaching needs.',
            'rows' => $rows,
            'meta' => ['empty' => empty($rows), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // TOP MISTAKES
    // ═══════════════════════════════════════════════════════

    public static function getTopMistakes(User $user, DashboardFilterData $filters): array
    {
        $items = [];
        if (Schema::hasTable('ai_trainer_mistakes')) {
            $items = AiTrainerMistake::select('mistake_type')
                ->selectRaw('COUNT(*) as cnt')
                ->where('detected_at', '>=', $filters->from)
                ->groupBy('mistake_type')
                ->orderByDesc('cnt')
                ->limit(8)->get()
                ->map(fn($m) => ['label' => ucfirst(str_replace('_', ' ', $m->mistake_type)), 'count' => $m->cnt])
                ->toArray();
        }

        return [
            'title' => 'Top Mistake Patterns',
            'subtitle' => 'Most common behavior issues this period',
            'items' => $items,
            'meta' => ['empty' => empty($items), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // RECENT SCORE CHANGES
    // ═══════════════════════════════════════════════════════

    public static function getRecentScoreChanges(User $user, DashboardFilterData $filters): array
    {
        $items = [];
        if (Schema::hasTable('ai_sales_scores')) {
            $items = AiSalesScore::orderByDesc('calculated_at')
                ->limit(12)->get()
                ->map(fn($s) => [
                    'id' => 'score_' . $s->id,
                    'entity_type' => $s->entity_type,
                    'entity_id' => $s->entity_id,
                    'score_type' => $s->score_type,
                    'new_score' => $s->numeric_score,
                    'label' => $s->label,
                    'reason' => ($s->reasons_json ?? [])[0] ?? null,
                    'updated_at' => $s->calculated_at?->toIso8601String(),
                    'updated_at_display' => $s->calculated_at?->diffForHumans(short: true) ?? '',
                ])->toArray();
        }

        return [
            'title' => 'Recent AI Updates',
            'subtitle' => 'Latest score and recommendation changes',
            'tooltip' => 'Shows recent AI decisions and updates.',
            'items' => $items,
            'meta' => ['empty' => empty($items), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // AI RECOMMENDATIONS
    // ═══════════════════════════════════════════════════════

    public static function getAiRecommendations(User $user, DashboardFilterData $filters): array
    {
        $items = [];
        if (Schema::hasTable('ai_trainer_recommendations')) {
            $isAdmin = $user->hasRole('master_admin', 'admin');
            $items = AiTrainerRecommendation::when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
                ->where('status', 'active')
                ->orderByDesc('id')
                ->limit(10)->get()
                ->map(fn($r) => [
                    'id' => 'rec_' . $r->id,
                    'entity_type' => $r->entity_type,
                    'entity_id' => $r->entity_id,
                    'recommendation' => $r->title,
                    'message' => $r->message,
                    'priority' => $r->recommendation_type === 'next_action' ? 'high' : 'medium',
                    'priority_label' => $r->recommendation_type === 'next_action' ? 'High' : 'Medium',
                    'created_at' => $r->created_at?->toIso8601String(),
                    'created_at_display' => $r->created_at?->diffForHumans(short: true) ?? '',
                ])->toArray();
        }

        return [
            'title' => 'AI Recommendations',
            'subtitle' => 'Suggested next actions',
            'tooltip' => 'Actions recommended by AI based on current data.',
            'items' => $items,
            'meta' => ['empty' => empty($items), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // UPCOMING REVENUE
    // ═══════════════════════════════════════════════════════

    public static function getUpcomingRevenue(User $user, DashboardFilterData $filters): array
    {
        $rows = [];
        if (Schema::hasTable('ai_sales_scores')) {
            $strong = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->where('numeric_score', '>=', 65)
                ->orderByDesc('numeric_score')
                ->limit(10)->get();

            foreach ($strong as $s) {
                $deal = Deal::find($s->entity_id);
                if (!$deal || $deal->status === 'charged') continue;

                $closer = $deal->closer ? User::find($deal->closer) : null;
                $rows[] = [
                    'deal_id' => $deal->id,
                    'deal_name' => $deal->owner_name ?? 'Unknown',
                    'deal_url' => '/deals',
                    'owner' => $closer ? ['id' => $closer->id, 'name' => $closer->name] : null,
                    'close_probability' => $s->numeric_score,
                    'close_probability_label' => self::probLabel($s->numeric_score),
                    'value' => (float) ($deal->fee ?? 0),
                    'value_display' => '$' . number_format((float) ($deal->fee ?? 0)),
                    'expected_timeframe' => $s->numeric_score >= 80 ? 'Within 7 days' : 'Within 14 days',
                ];
            }
        }

        return [
            'title' => 'Upcoming Revenue',
            'subtitle' => 'Deals likely to close soon',
            'tooltip' => 'High-probability deals expected to convert.',
            'rows' => $rows,
            'meta' => ['empty' => empty($rows), 'generated_at' => now()->toIso8601String()],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // DRILLDOWN
    // ═══════════════════════════════════════════════════════

    public static function getDrilldown(string $entityType, int $entityId): array
    {
        $score = AiSalesScore::forEntity($entityType, $entityId,
            $entityType === 'deal' ? 'close_probability' : 'lead_score');

        if (!$score) {
            return ['data' => null, 'meta' => ['error' => true, 'message' => 'No AI insight available for this record.']];
        }

        $entity = $entityType === 'deal' ? Deal::find($entityId) : Lead::find($entityId);
        $name = $entity?->owner_name ?? 'Unknown';

        return [
            'title' => 'AI Insight Details',
            'entity' => [
                'type' => $entityType,
                'id' => $entityId,
                'name' => $name,
                'url' => '/' . ($entityType === 'deal' ? 'deals' : 'leads'),
            ],
            'score_block' => [
                'score_type' => $score->score_type,
                'value' => $score->numeric_score,
                'label' => $entityType === 'deal' ? self::probLabel($score->numeric_score) : self::leadLabel($score->label),
            ],
            'key_reasons' => $score->reasons_json ?? [],
            'risk_signals' => $score->risks_json ?? [],
            'recommended_next_action' => ($score->recommendations_json ?? [])[0] ?? 'Review this record',
            'last_updated_at' => $score->calculated_at?->toIso8601String(),
            'last_updated_display' => $score->calculated_at?->diffForHumans() ?? '--',
            'confidence_level' => $score->confidence_score ?? 70,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════

    private static function card(string $key, string $title, string $subtitle, $value, ?string $badge, ?string $badgeVariant, string $accent, string $tooltip, string $prefix = ''): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'value' => $value,
            'value_display' => $prefix . (is_numeric($value) ? number_format($value) : $value),
            'prefix' => $prefix,
            'badge' => $badge,
            'badge_variant' => $badgeVariant,
            'accent_color' => $accent,
            'tooltip' => $tooltip,
            'state' => ['loading' => false, 'empty' => $value === 0, 'stale' => false, 'updating' => false],
        ];
    }

    private static function meta(User $user, DashboardFilterData $filters): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'stale' => false,
            'role' => $user->role,
            'filters_applied' => $filters->toMeta(),
        ];
    }

    private static function probLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Very Strong',
            $score >= 60 => 'Strong',
            $score >= 40 => 'Moderate',
            $score >= 20 => 'Weak',
            default => 'At Risk',
        };
    }

    private static function leadLabel(?string $label): string
    {
        return match ($label) {
            'hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold', default => 'At Risk',
        };
    }

    private static function scoreCount(string $type, string $scoreType, string $label): int
    {
        if (!Schema::hasTable('ai_sales_scores')) return 0;
        return AiSalesScore::where('entity_type', $type)->where('score_type', $scoreType)->where('label', $label)->count();
    }

    private static function scoreCountAbove(string $type, string $scoreType, int $min): int
    {
        if (!Schema::hasTable('ai_sales_scores')) return 0;
        return AiSalesScore::where('entity_type', $type)->where('score_type', $scoreType)->where('numeric_score', '>=', $min)->count();
    }

    private static function scoreCountIn(string $type, string $scoreType, array $labels): int
    {
        if (!Schema::hasTable('ai_sales_scores')) return 0;
        return AiSalesScore::where('entity_type', $type)->where('score_type', $scoreType)->whereIn('label', $labels)->count();
    }
}
