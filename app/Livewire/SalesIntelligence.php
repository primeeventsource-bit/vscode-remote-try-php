<?php

namespace App\Livewire;

use App\Models\AiSalesScore;
use App\Models\AiTrainerMistake;
use App\Models\AiTrainerRecommendation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\AiTrainerService;
use App\Services\AI\LeadScoringService;
use App\Services\AI\DealScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $isMaster = $user->hasRole('master_admin');
        $isAdmin = $user->hasRole('master_admin', 'admin', 'admin_limited');
        $isFronter = in_array($user->role, ['fronter', 'fronter_panama']);
        $isCloser = in_array($user->role, ['closer', 'closer_panama']);

        $from = match ($this->dateRange) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'month' => now()->startOfMonth(),
            default => now()->subDays(30),
        };

        // ── KPI Summary ───────────────────────────────
        $leadQuery = Lead::query();
        $dealQuery = Deal::query();

        if ($isFronter) {
            $leadQuery->where('assigned_to', $user->id);
            $dealQuery->where('fronter', $user->id);
        } elseif ($isCloser) {
            $dealQuery->where('closer', $user->id);
        }
        if ($this->ownerFilter !== 'all') {
            $leadQuery->where('assigned_to', (int) $this->ownerFilter);
            $dealQuery->where(function ($q) {
                $q->where('closer', (int) $this->ownerFilter)->orWhere('fronter', (int) $this->ownerFilter);
            });
        }

        $activeLeads = (clone $leadQuery)->whereNull('disposition')
            ->orWhereNotIn('disposition', ['Wrong Number', 'Disconnected'])->count();

        $kpis = [
            'active_leads' => $activeLeads,
            'hot_leads' => 0,
            'likely_close' => 0,
            'at_risk_deals' => 0,
            'weighted_forecast' => 0,
            'overdue_followups' => 0,
        ];

        // Score-based KPIs
        if (Schema::hasTable('ai_sales_scores')) {
            $kpis['hot_leads'] = AiSalesScore::where('entity_type', 'lead')
                ->where('score_type', 'lead_score')
                ->where('label', 'hot')->count();

            $kpis['likely_close'] = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->where('numeric_score', '>=', 70)->count();

            $kpis['at_risk_deals'] = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->whereIn('label', ['at_risk', 'weak'])->count();

            // Weighted forecast = sum of (deal fee * close probability / 100)
            $dealScores = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->get();
            $forecast = 0;
            foreach ($dealScores as $ds) {
                $deal = Deal::find($ds->entity_id);
                if ($deal) {
                    $forecast += ((float) ($deal->fee ?? 0)) * ($ds->numeric_score / 100);
                }
            }
            $kpis['weighted_forecast'] = $forecast;
        }

        // Overdue follow-ups (leads with callback in past + undisposed leads > 3 days old)
        $kpis['overdue_followups'] = Lead::where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('disposition', 'Callback')->where('callback_date', '<', now());
            })->orWhere(function ($q2) {
                $q2->whereNull('disposition')->where('created_at', '<', now()->subDays(3));
            });
        })->when($isFronter, fn($q) => $q->where('assigned_to', $user->id))->count();

        // ── At-Risk Deals Table ───────────────────────
        $atRiskDeals = collect();
        if (Schema::hasTable('ai_sales_scores')) {
            $atRiskScores = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')
                ->whereIn('label', ['at_risk', 'weak'])
                ->orderBy('numeric_score')
                ->limit(15)
                ->get();

            $atRiskDeals = $atRiskScores->map(function ($s) {
                $deal = Deal::find($s->entity_id);
                if (!$deal) return null;
                $closer = $deal->closer ? User::find($deal->closer) : null;
                return [
                    'id' => $deal->id,
                    'owner_name' => $deal->owner_name ?? 'Unknown',
                    'closer_name' => $closer?->name ?? '--',
                    'fee' => (float) ($deal->fee ?? 0),
                    'close_pct' => $s->numeric_score,
                    'label' => $s->label,
                    'risks' => $s->risks_json ?? [],
                    'next_action' => ($s->recommendations_json ?? [])[0] ?? 'Review deal',
                    'updated_at' => $deal->updated_at,
                ];
            })->filter()->values();
        }

        // ── Hottest Leads Table ───────────────────────
        $hottestLeads = collect();
        if (Schema::hasTable('ai_sales_scores')) {
            $hotScores = AiSalesScore::where('entity_type', 'lead')
                ->where('score_type', 'lead_score')
                ->where('numeric_score', '>=', 50)
                ->orderByDesc('numeric_score')
                ->limit(15)
                ->get();

            $hottestLeads = $hotScores->map(function ($s) {
                $lead = Lead::find($s->entity_id);
                if (!$lead) return null;
                $assigned = $lead->assigned_to ? User::find($lead->assigned_to) : null;
                return [
                    'id' => $lead->id,
                    'owner_name' => $lead->owner_name ?? 'Unknown',
                    'assigned_name' => $assigned?->name ?? 'Unassigned',
                    'score' => $s->numeric_score,
                    'label' => $s->label,
                    'reasons' => $s->reasons_json ?? [],
                    'risks' => $s->risks_json ?? [],
                    'next_action' => ($s->recommendations_json ?? [])[0] ?? 'Review lead',
                ];
            })->filter()->values();
        }

        // ── Follow-Up Queue ───────────────────────────
        $followupQueue = Lead::query()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('disposition', 'Callback')->where('callback_date', '<', now()->addHours(24));
                })->orWhere(function ($q2) {
                    $q2->whereNull('disposition')->where('created_at', '<', now()->subDays(2));
                })->orWhere(function ($q2) {
                    $q2->where('disposition', 'Left Voice Mail')->where('updated_at', '<', now()->subDays(1));
                });
            })
            ->when($isFronter, fn($q) => $q->where('assigned_to', $user->id))
            ->orderByRaw("CASE WHEN disposition = 'Callback' AND callback_date < GETDATE() THEN 0 ELSE 1 END")
            ->orderBy('updated_at')
            ->limit(20)
            ->get()
            ->map(function ($lead) {
                $assigned = $lead->assigned_to ? User::find($lead->assigned_to) : null;
                $priority = 'Medium';
                $channel = 'Call';
                if ($lead->disposition === 'Callback' && $lead->callback_date?->isPast()) {
                    $priority = 'High';
                } elseif (!$lead->disposition && $lead->created_at?->diffInDays(now()) > 5) {
                    $priority = 'High';
                } elseif ($lead->disposition === 'Left Voice Mail') {
                    $priority = 'Low';
                    $channel = 'Call';
                }
                return [
                    'id' => $lead->id,
                    'owner_name' => $lead->owner_name ?? 'Unknown',
                    'type' => 'Lead',
                    'assigned_name' => $assigned?->name ?? 'Unassigned',
                    'priority' => $priority,
                    'channel' => $channel,
                    'disposition' => $lead->disposition ?? 'Undisposed',
                    'age_days' => $lead->created_at?->diffInDays(now()) ?? 0,
                    'callback_date' => $lead->callback_date,
                ];
            });

        // ── Close Probability Distribution ────────────
        $probBins = ['80-100' => 0, '60-79' => 0, '40-59' => 0, '20-39' => 0, '0-19' => 0];
        $probRevenue = ['80-100' => 0, '60-79' => 0, '40-59' => 0, '20-39' => 0, '0-19' => 0];
        if (Schema::hasTable('ai_sales_scores')) {
            $allDealScores = AiSalesScore::where('entity_type', 'deal')
                ->where('score_type', 'close_probability')->get();
            foreach ($allDealScores as $ds) {
                $s = $ds->numeric_score;
                $deal = Deal::find($ds->entity_id);
                $fee = (float) ($deal->fee ?? 0);
                $bin = match (true) {
                    $s >= 80 => '80-100',
                    $s >= 60 => '60-79',
                    $s >= 40 => '40-59',
                    $s >= 20 => '20-39',
                    default => '0-19',
                };
                $probBins[$bin]++;
                $probRevenue[$bin] += $fee;
            }
        }

        // ── Top Mistakes (admin) ──────────────────────
        $topMistakes = collect();
        $coachingWatchlist = collect();
        if ($isAdmin && Schema::hasTable('ai_trainer_mistakes')) {
            $topMistakes = AiTrainerMistake::select('mistake_type')
                ->selectRaw('COUNT(*) as cnt')
                ->where('detected_at', '>=', $from)
                ->groupBy('mistake_type')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $coachingWatchlist = AiTrainerMistake::select('user_id')
                ->selectRaw('COUNT(*) as cnt')
                ->where('detected_at', '>=', $from)
                ->whereNull('resolved_at')
                ->groupBy('user_id')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $u = User::find($row->user_id);
                    if (!$u) return null;
                    $topMistake = AiTrainerMistake::where('user_id', $row->user_id)
                        ->whereNull('resolved_at')
                        ->orderByDesc('detected_at')
                        ->first();
                    return [
                        'user_id' => $u->id,
                        'name' => $u->name,
                        'role' => $u->role,
                        'avatar' => $u->avatar,
                        'color' => $u->color,
                        'mistake_count' => $row->cnt,
                        'top_weakness' => $topMistake ? ucfirst(str_replace('_', ' ', $topMistake->mistake_type)) : '--',
                        'severity' => $topMistake?->severity ?? 'low',
                    ];
                })->filter()->values();
        }

        // ── Recent AI Recommendations ─────────────────
        $recentRecs = collect();
        if (Schema::hasTable('ai_trainer_recommendations')) {
            $recentRecs = AiTrainerRecommendation::when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        // ── Recent Score Changes ──────────────────────
        $recentScoreChanges = collect();
        if (Schema::hasTable('ai_sales_scores')) {
            $recentScoreChanges = AiSalesScore::orderByDesc('calculated_at')
                ->limit(10)
                ->get()
                ->map(fn($s) => [
                    'entity_type' => $s->entity_type,
                    'entity_id' => $s->entity_id,
                    'score_type' => $s->score_type,
                    'score' => $s->numeric_score,
                    'label' => $s->label,
                    'calculated_at' => $s->calculated_at,
                ]);
        }

        $users = $isAdmin ? User::orderBy('name')->get() : collect();

        return view('livewire.sales-intelligence', compact(
            'user', 'isMaster', 'isAdmin', 'isFronter', 'isCloser',
            'kpis', 'atRiskDeals', 'hottestLeads', 'followupQueue',
            'probBins', 'probRevenue', 'topMistakes', 'coachingWatchlist',
            'recentRecs', 'recentScoreChanges', 'users'
        ));
    }
}
