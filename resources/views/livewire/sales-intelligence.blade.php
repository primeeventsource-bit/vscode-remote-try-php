<div class="p-5">
    {{-- ═══════════════════════════════════════════════
         ROW 0 — HEADER
    ═══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Sales Intelligence</h2>
            <p class="text-xs text-crm-t3 mt-1">Live AI scoring, follow-up urgency, close probability, and coaching insights</p>
        </div>
        <x-dashboard.filter-bar
            :dateRange="$dateRange"
            :ownerFilter="$ownerFilter"
            :users="$users"
            :showOwnerFilter="$isAdmin"
            :showExport="$isMaster"
        />
    </div>

    {{-- ═══════════════════════════════════════════════
         ROW 1 — KPI SUMMARY CARDS
    ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ ($isAdmin || $isCloser) ? '6' : '5' }} gap-3 mb-6">
        <x-dashboard.kpi-summary-card
            title="Active Leads"
            subtitle="Leads currently in your pipeline"
            tooltip="Shows all leads that are not closed or marked not interested."
            :value="$kpis['active_leads']"
            accentColor="blue"
        />
        <x-dashboard.kpi-summary-card
            title="Hot Leads"
            subtitle="Updated by AI scoring engine"
            tooltip="AI-detected leads with strong engagement and high conversion potential."
            :value="$kpis['hot_leads']"
            accentColor="red"
            badge="Hot"
            badgeVariant="hot"
        />
        <x-dashboard.kpi-summary-card
            title="Likely to Close"
            subtitle="Based on deal scoring"
            tooltip="Deals with an AI-estimated close probability above 80%."
            :value="$kpis['likely_close']"
            accentColor="emerald"
            badge="80%+"
            badgeVariant="strong"
        />
        <x-dashboard.kpi-summary-card
            title="At-Risk Deals"
            subtitle="AI risk signals detected"
            tooltip="Deals flagged due to inactivity, weak signals, or missing follow-up."
            :value="$kpis['at_risk_deals']"
            accentColor="amber"
            :badge="$kpis['at_risk_deals'] > 0 ? 'Needs Attention' : null"
            badgeVariant="at_risk"
        />
        @if($isAdmin || $isCloser)
            <x-dashboard.kpi-summary-card
                title="Weighted Forecast"
                subtitle="AI-adjusted projection"
                tooltip="Estimated revenue based on deal values weighted by close probability."
                :value="$kpis['weighted_forecast']"
                prefix="$"
                accentColor="purple"
            />
        @endif
        <x-dashboard.kpi-summary-card
            title="Overdue Follow-Ups"
            subtitle="Based on follow-up intelligence"
            tooltip="Records that require immediate follow-up based on AI timing signals."
            :value="$kpis['overdue_followups']"
            :accentColor="$kpis['overdue_followups'] > 0 ? 'red' : 'gray'"
            :badge="$kpis['overdue_followups'] > 0 ? 'Urgent' : null"
            badgeVariant="urgent"
        />
    </div>

    {{-- ═══════════════════════════════════════════════
         ROW 2 — CLOSE PROBABILITY + PIPELINE RISK
    ═══════════════════════════════════════════════ --}}
    @if(!$isFronter)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2">
            <x-dashboard.deal-probability-chart :bins="$probBins" :revenue="$probRevenue" />
        </div>
        <div>
            @php
                $riskCategories = [];
                foreach ($atRiskDeals as $d) {
                    foreach ($d['risks'] ?? [] as $r) {
                        $key = Str::limit($r, 30);
                        $riskCategories[$key] = ($riskCategories[$key] ?? 0) + 1;
                    }
                }
                arsort($riskCategories);
            @endphp
            <x-dashboard.pipeline-risk-chart :risks="$riskCategories" />
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         ROW 3 — AT-RISK DEALS + HOTTEST LEADS
    ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        @if(!$isFronter)
            <x-dashboard.at-risk-deals-table :deals="$atRiskDeals" />
        @endif
        <x-dashboard.hottest-leads-table :leads="$hottestLeads" />
        @if($isFronter)
            {{-- Fronters get the follow-up queue in this row instead --}}
            <x-dashboard.followup-queue-table :items="$followupQueue" />
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════
         ROW 4 — FOLLOW-UP QUEUE (non-fronter)
    ═══════════════════════════════════════════════ --}}
    @if(!$isFronter)
        <div class="mb-6">
            <x-dashboard.followup-queue-table :items="$followupQueue" />
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         ROW 5 — REP COACHING + TOP MISTAKES (Admin)
    ═══════════════════════════════════════════════ --}}
    @if($isAdmin)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <x-dashboard.rep-coaching-watchlist :users="$coachingWatchlist" />
        <x-dashboard.top-mistakes-chart :mistakes="$topMistakes" />
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         ROW 6 — RECENT ACTIVITY FEEDS
    ═══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-dashboard.recent-score-changes-feed :items="$recentScoreChanges" />
        <x-dashboard.ai-recommendations-feed :items="$recentRecs" />
    </div>

    {{-- ═══════════════════════════════════════════════
         DRILLDOWN DRAWER (global, opens on row click)
    ═══════════════════════════════════════════════ --}}
    <x-dashboard.ai-insight-drilldown-drawer />
</div>
